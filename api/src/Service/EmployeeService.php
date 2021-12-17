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
     * Sets the correct @organization for the employee in the gateway
     *
     * @param array $employee
     * @return array
     */
    public function checkOrganization(array $employee): array
    {
        // If this employee has an organization
        if (array_key_exists('@uri', $employee['organization']) && $employee['@organization'] !== $employee['organization']['@uri']) {
//            var_dump('org '.$employee['organization']['@uri']);
            $employeeUpdate['@organization'] = $employee['organization']['@uri'];
            $employeeUpdate['person'] = $employee['person']['id'];
            $employee = $this->commonGroundService->updateResource($employeeUpdate, ['component' => 'gateway', 'type' => 'employees', 'id' => $employee['id']]);
        }

        return $employee;
    }
}
