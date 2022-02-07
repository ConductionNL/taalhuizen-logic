<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;

class StudentService
{
    private CommonGroundService $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;
    }

    /**
     * Sets the correct values for the student in the gateway
     *
     * @param array $student
     * @return array
     */
    public function checkStudent(array $student): array
    {
        if ($student['@organization'] !== $student['languageHouse']['@uri']
            || (!empty($student['@owner']) && array_key_exists('@uri', $student['intake']) && $student['intake'] !== 'ACCEPTED')) {
            $studentUpdate = $this->checkLanguageHouse($student);
            $studentUpdate = $this->checkIntakeStatus($student, $studentUpdate); //todo array merge?
            $studentUpdate = $this->checkMentor($student, $studentUpdate);

            $studentUpdate['person'] = $student['person']['id'];

            $student = $this->commonGroundService->updateResource($studentUpdate, ['component' => 'gateway', 'type' => 'students', 'id' => $student['id']]);
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
        if (!empty($student['@owner']) && array_key_exists('@uri', $student['intake'])) {
            $studentUpdate['intake'] = [
                'status' => 'ACCEPTED',
                'didSignPermissionForm' => $student['intake']['didSignPermissionForm'],
                'hasPermissionToShareDataWithProviders' => $student['intake']['hasPermissionToShareDataWithProviders'],
                'hasPermissionToShareDataWithLibraries' => $student['intake']['hasPermissionToShareDataWithLibraries'],
                'hasPermissionToSendInformationAboutLibraries' => $student['intake']['hasPermissionToSendInformationAboutLibraries']
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
    private function checkMentor(array $student, array $studentUpdate): array
    {
        // Note: A public registration is done anonymous and has no @owner. A manual registration has an @owner.
        // If manual registration, set mentor to the employee who did the registration
        if (!empty($student['@owner'])) {
            // Find the user that created this student resource (check for $student['@owner'] = url or, else $student['@owner'] = uuid)
            if (!$user = $this->commonGroundService->isResource($student['@owner'])) {
                $user = $this->commonGroundService->getResource($this->commonGroundService->cleanUrl(['component' => 'uc', 'type' => 'users', 'id' => $student['@owner']]));
            }
            // Get the employee using the person from this user.
            $existingEmployees = $this->commonGroundService->getResourceList(['component' => 'gateway', 'type' => 'employees'], ['person._uri' => $user['person']])['results']; // add to query?: 'person.user.username' => $existingUser['username'] OR: 'person.emails.email' => $existingUser['username']
            var_dump(count($existingEmployees));
            if (count($existingEmployees) > 0) {
                $employee = $existingEmployees[0];
                $studentUpdate['mentor'] = $employee['id'];
            }
        }

        return $studentUpdate;
    }
}
