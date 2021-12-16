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
        $studentUpdate = $this->checkLanguageHouse($student);
        $studentUpdate = $this->checkIntakeStatus($student, $studentUpdate); //todo array merge?

        if ($student['organization'] !== $student['languageHouse']['@uri'] || (!empty($student['@owner']) && array_key_exists('@uri', $student['intake']) && $student['intake'] !== 'ACCEPTED')) {
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
            $studentUpdate['person'] = $student['person']['id'];
            $studentUpdate['languageHouse'] = $student['languageHouse']['id'];
        }

        return $studentUpdate;
    }

    /**
     * Returns a student body with the correct intake status for updating the student in the gateway
     *
     * @param array $student
     * @return array
     */
    private function checkIntakeStatus(array $student, array $studentUpdate): array
    {
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
}
