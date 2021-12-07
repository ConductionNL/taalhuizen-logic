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
     * Checks if we need to find a LanguageHouse with the students address and if so update the student and return the updated student.
     *
     * @param array $student
     * @return array
     */
    public function checkLanguageHouse(array $student): array
    {
        // If this student has no LanguageHouse
        if (!array_key_exists('id', $student['languageHouse'])) {
            // Find a LanguageHouse with the address of this student
            $postalCodes = $this->commonGroundService->getResourceList(['component' => 'gateway', 'type' => 'postal_codes'], ['code' => substr($student['addresses'][0]['postalCode'], 0, 4)])['results'];
            if (count($postalCodes) > 0) {
                $languageHouse = $postalCodes[0]['languageHouse'];

                // If we found a LanguageHouse connect it to the student.
                $updateStudent = [
                    'languageHouse' => $languageHouse['id'],
                    'person' => $student['person']['id']
                ];
                $student = $this->commonGroundService->updateResource($updateStudent, ['component' => 'gateway', 'type' => 'students', 'id' => $student['id']]);
            }
        }

        return $student;
    }
}
