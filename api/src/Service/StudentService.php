<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class StudentService
{
    private CommonGroundService $commonGroundService;
    private ParameterBagInterface $parameterBag;

    public function __construct(CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag)
    {
        $this->commonGroundService = $commonGroundService;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Sets the correct values for the student in the gateway when a new student object is created
     *
     * @param array $student
     * @return array
     */
    public function checkStudent(array $student): array
    {
        if ($student['@organization'] !== $student['languageHouse']['@uri']
            || (!empty($student['@owner']) && (empty($student['intake']) || (array_key_exists('@uri', $student['intake']) && $student['intake']['status'] !== 'ACCEPTED')))) {
            $studentUpdate = $this->checkLanguageHouse($student);
            $studentUpdate = $this->checkIntakeStatus($student, $studentUpdate); //todo array merge?
            $studentUpdate = $this->checkMentorAndTeam($student, $studentUpdate);

            $studentUpdate['person'] = $student['person']['id'];
            if (empty($student['@owner'])) {
                $studentUpdate['@owner'] = null; // Make sure we do not set the owner to the Taalhuizen-logic user for a public registration on creation.
            }

            $component = $this->parameterBag->get('components')['gateway'];
            $url = $component['location'].'/students/'.$student['id'];
            $content = json_encode($studentUpdate);
            // @owner will be removed from the body if we use cgb updateResource instead of callservice...
            $response = $this->commonGroundService->callService($component, $url, $content, [], [], false, 'PUT');
            // Callservice returns array on error
            if (is_array($response)) {
                //todo?
//                var_dump($response);
            }
            $student = json_decode($response->getBody()->getContents(), true);
        }
        return $student;
    }

    /**
     * Returns a student body with the correct @organization for updating the student in the gateway
     *
     * @param array $student
     * @return array
     */
    private function checkLanguageHouse(array $student): array
    {
        $studentUpdate = [];
        // If this student has no LanguageHouse
        if (!array_key_exists('@uri', $student['languageHouse'])) {
            // todo: this is disabled for now
//            // Find a LanguageHouse with the address of this student
//            $postalCodes = $this->commonGroundService->getResourceList(['component' => 'gateway', 'type' => 'postal_codes'], ['code' => substr($student['person']['addresses'][0]['postalCode'], 0, 4)])['results'];
//            if (count($postalCodes) > 0) {
//                $languageHouse = $postalCodes[0]['languageHouse'];
//
//                // If we found a LanguageHouse connect it to the student.
//                $studentUpdate = [
//                    'languageHouse' => $languageHouse['id'],
//                    'person' => $student['person']['id']
//                ];
//            }
            //todo, if we put this ^ back also make sure to add the part below somehow
        }
        // If this student does have a LanguageHouse & intake status == PENDING (public registration Release 3 Scenario 5.0)
//        elseif (array_key_exists('intake', $student) && array_key_exists('status', $student['intake']) && $student['intake']['status'] == 'PENDING') {
        else {
//            var_dump('org '.$student['languageHouse']['@uri']);
            $studentUpdate['@organization'] = $student['languageHouse']['@uri'];
//            $studentUpdate['languageHouse'] = $student['languageHouse']['id']; // This attribute is immutable
        }

        return $studentUpdate;
    }

    /**
     * Returns a student body with the correct intake status for updating the student in the gateway
     *
     * @param array $student
     * @param array $studentUpdate
     * @return array
     */
    private function checkIntakeStatus(array $student, array $studentUpdate): array
    {
        // Note: A public registration is done anonymous and has no @owner. A manual registration has an @owner.
        // If manual registration, set intake status to accepted
        if (!empty($student['@owner']) && (empty($student['intake']) || (array_key_exists('@uri', $student['intake']) && $student['intake']['status'] !== 'ACCEPTED'))) {
            $studentUpdate['intake'] = [
                'status' => 'ACCEPTED',
                'didSignPermissionForm' => $student['intake']['didSignPermissionForm'] ?? false,
                'hasPermissionToShareDataWithProviders' => $student['intake']['hasPermissionToShareDataWithProviders'] ?? false,
                'hasPermissionToShareDataWithLibraries' => $student['intake']['hasPermissionToShareDataWithLibraries'] ?? false,
                'hasPermissionToSendInformationAboutLibraries' => $student['intake']['hasPermissionToSendInformationAboutLibraries'] ?? false
            ];
        }

        return $studentUpdate;
    }

    /**
     * Returns a student body with the correct mentor employee for updating the student in the gateway
     *
     * @param array $student
     * @param array $studentUpdate
     * @return array
     */
    private function checkMentorAndTeam(array $student, array $studentUpdate): array
    {
        // Note: A public registration is done anonymous and has no @owner. A manual registration has an @owner.
        // If manual registration, set mentor to the employee who did the registration
        if (!empty($student['@owner'])) {
            // Find the user that created this student resource (check for $student['@owner'] = url or, else $student['@owner'] = uuid)
            if (!$user = $this->commonGroundService->isResource($student['@owner'])) {
                $user = $this->commonGroundService->getResource($this->commonGroundService->cleanUrl(['component' => 'uc', 'type' => 'users', 'id' => $student['@owner']]), [], false);
            }
            // Get the employee using the person from this user.
            $existingEmployees = $this->commonGroundService->getResourceList(['component' => 'gateway', 'type' => 'employees'], ['person._uri' => $user['person']], false)['results'];
            if (count($existingEmployees) > 0) {
                $employee = $existingEmployees[0];
                $studentUpdate['mentor'] = $employee['id'];
                if (!empty($employee['teams']) && empty($student['team'])) {
                    $studentUpdate['team'] = $employee['teams'][0]['id'];
                }
            }
        }

        return $studentUpdate;
    }

    /**
     * Sets the correct values for the student in the gateway when a public registration is accepted
     *
     * @param array $student
     * @return array
     */
    public function acceptedRegistration(array $student): array
    {
        // If no mentor is set for this student, we have an owner to get the mentor employee with and the status of this student is ACCEPTED...
        if (empty($student['mentor']) && !empty($student['@owner']) && array_key_exists('@uri', $student['intake']) && $student['intake']['status'] === 'ACCEPTED') {
            // Set the mentor for this student (without changing the team if the student already has a team)
            $studentUpdate = $this->checkLanguageHouse($student);
            $studentUpdate = $this->checkMentorAndTeam($student, $studentUpdate); // todo: owner is not set to the user that accepted the registration... so this does not work at the moment

            $studentUpdate['person'] = $student['person']['id'];

            $student = $this->commonGroundService->updateResource($studentUpdate, ['component' => 'gateway', 'type' => 'students', 'id' => $student['id']]);
        }

        return $student;
    }
}
