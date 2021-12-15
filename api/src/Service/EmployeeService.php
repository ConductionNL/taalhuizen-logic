<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;

class EmployeeService
{
    private CommonGroundService $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;
    }

    /**
     * Checks if we need to find a LanguageHouse with the students address and if so update the student and return the updated student.
     *
     * @param array $employee
     * @return array
     */
    public function checkOrganization(array $employee): array
    {
        // If this student has no LanguageHouse
        if (!array_key_exists('id', $employee['languageHouse'])) {
            // todo: this is disabled for now
//            // Find a LanguageHouse with the address of this student
//            $postalCodes = $this->commonGroundService->getResourceList(['component' => 'gateway', 'type' => 'postal_codes'], ['code' => substr($student['person']['addresses'][0]['postalCode'], 0, 4)])['results'];
//            if (count($postalCodes) > 0) {
//                $languageHouse = $postalCodes[0]['languageHouse'];
//
//                // If we found a LanguageHouse connect it to the student.
//                $updateStudent = [
//                    'languageHouse' => $languageHouse['id'],
//                    'person' => $student['person']['id']
//                ];
//                $student = $this->commonGroundService->updateResource($updateStudent, ['component' => 'gateway', 'type' => 'students', 'id' => $student['id']]);
//            }
            //todo, if we put this ^ back also make sure to add the part below somehow
        }
        // If this student does have a LanguageHouse & intake status == PENDING (public registration Release 3 Scenario 5.0)
        elseif (array_key_exists('intake', $employee) && array_key_exists('status', $employee['intake']) && $employee['intake']['status'] == 'PENDING') {
            // todo: update ObjectEntity->organzation to LanguageHouse
//            var_dump('org '.$student['languageHouse']['@uri']);
            $objectEntity = ['@organization' => $employee['organization']['@uri']];
            $employee = $this->commonGroundService->updateResource($objectEntity, ['component' => 'gateway', 'type' => 'employees', 'id' => $employee['id']]);
        }

        return $employee;
    }
}
