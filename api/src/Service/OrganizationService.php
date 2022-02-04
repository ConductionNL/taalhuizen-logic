<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;

class OrganizationService
{
    private CommonGroundService $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;
    }

    /**
     * Creates a team for an languageHouse organization.
     *
     * @param array  $organization The organization to save a team for
     *
     * @return array The created Team
     */
    public function createTeamForLanguageHouse(array $organization): array
    {
        $team = [
            "name" => "Team ".$organization['name'],
            "type" => "team",
            "parentOrganization" => $organization['id']
        ];

        return $this->commonGroundService->createResource($team, ['component' => 'gateway', 'type' => 'organizations']);
    }
}
