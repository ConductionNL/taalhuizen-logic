<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;

class UserGroupService
{
    private CommonGroundService $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;
    }

    /**
     * Saves the user groups for an organization. Different groups depending on the organization type.
     *
     * @param array  $organization The organization to save user groups for
     *
     * @return array The created UserGroups
     */
    public function saveUserGroups(array $organization): array
    {
        $userGroups = [];
        switch ($organization['type']) {
            case 'taalhuis':
                $userGroups = $this->saveLanguageHouseUserGroups($organization, $userGroups);
                break;
            case 'aanbieder':
                $userGroups = $this->saveProviderUserGroups($organization, $userGroups);
                break;
            default:
                break;
        }

        return $userGroups;
    }

    /**
     * Deletes the user groups for an organization. If organization type is 'taalhuis' or 'aanbieder'.
     *
     * @param array  $organization The organization to delete user groups for
     *
     * @return false|mixed False if failed, array of userGroups if successful
     */
    public function deleteUserGroups(array $organization)
    {
        switch ($organization['type']) {
            case 'taalhuis':
            case 'aanbieder':
                $userGroups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $organization['id']])['hydra:member'];
                if ($userGroups > 0) {
                    foreach ($userGroups as $userGroup) {
                        $this->commonGroundService->deleteResource(null, ['component'=>'uc', 'type' => 'groups', 'id' => $userGroup['id']]);
                    }
                }
                return $userGroups;
            default:
                return false;
        }
    }

    /**
     * Finds user groups in an array of user groups by their name.
     *
     * @param array  $userGroups The user group array
     * @param string $name       The name to look for
     *
     * @return array The existing user group (if it exists)
     */
    private function findUserGroupsByName(array $userGroups, string $name): ?array
    {
        foreach ($userGroups as $userGroup) {
            if ($userGroup['name'] == $name) {
                return $userGroup;
            }
        }

        return [];
    }

    /**
     * Gets the scopes of a specific userGroup
     *
     * @param string $userGroupId
     * @return array
     */
    private function getScopes(string $userGroupId): array
    {
        $scopes = $this->commonGroundService->getResource(['component' => 'uc', 'type' => 'groups', 'id' => $userGroupId])['scopes'];
        foreach ($scopes as &$scope) {
            $scope = '/scopes/'.$scope['id'];
        }
        return $scopes;
    }

    /**
     * Saves the user groups for a language house.
     *
     * @param array $languageHouse The language house the groups have to be saved for
     * @param array $userGroups    The user groups that already exist for the language house
     *
     * @return array The user groups that exist for the language house
     */
    private function saveLanguageHouseUserGroups(array $languageHouse, array $userGroups): array
    {
        $existingUserGroups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $languageHouse['id']])])['hydra:member'];
        $userGroupCoordinator = $this->findUserGroupsByName($existingUserGroups, 'TAALHUIS_COORDINATOR');
        $userGroupEmployee = $this->findUserGroupsByName($existingUserGroups, 'TAALHUIS_EMPLOYEE');

        $userGroups = $this->saveLanguageHouseCoordinatorGroup($languageHouse, $userGroups, $userGroupCoordinator);
        $userGroups = $this->saveLanguageHouseEmployeeGroup($languageHouse, $userGroups, $userGroupEmployee);

        return $userGroups;
    }

    /**
     * Saves a coordinator group for a language house.
     *
     * @param array      $languageHouse        The language house to save the user group for
     * @param array      $userGroups           The user groups that already exist for the language house
     * @param array|null $userGroupCoordinator The existing coordinator user group
     *
     * @return array The user groups that exist for the language house
     */
    private function saveLanguageHouseCoordinatorGroup(array $languageHouse, array $userGroups, array $userGroupCoordinator): array
    {
        $coordinator = [
            'organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $languageHouse['id']]),
            'name'         => 'TAALHUIS_COORDINATOR',
            'description'  => 'UserGroup coordinator of '.$languageHouse['name'],
            'scopes'       => $this->getScopes('6c01ae98-5c9c-49e3-b99f-d74ad96074ab'),
        ];
        if ($userGroupCoordinator) {
            $userGroups[] = $this->commonGroundService->updateResource($coordinator, ['component' => 'uc', 'type' => 'groups', 'id' => $userGroupCoordinator['id']]);
        } else {
            $userGroups[] = $this->commonGroundService->saveResource($coordinator, ['component' => 'uc', 'type' => 'groups']);
        }

        return $userGroups;
    }

    /**
     * Saves a employee group for a language house.
     *
     * @param array      $languageHouse     The language house to save the user group for
     * @param array      $userGroups        The user groups that already exist for the language house
     * @param array|null $userGroupEmployee The existing employee user group
     *
     * @return array The user groups that exist for the language house
     */
    private function saveLanguageHouseEmployeeGroup(array $languageHouse, array $userGroups, array $userGroupEmployee): array
    {
        $employee = [
            'organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $languageHouse['id']]),
            'name'         => 'TAALHUIS_EMPLOYEE',
            'description'  => 'UserGroup employee of '.$languageHouse['name'],
            'scopes'       => $this->getScopes('f88efb3f-5a88-475d-b7b0-8865028487e2'),
        ];
        if ($userGroupEmployee) {
            $userGroups[] = $this->commonGroundService->updateResource($employee, ['component' => 'uc', 'type' => 'groups', 'id' => $userGroupEmployee['id']]);
        } else {
            $userGroups[] = $this->commonGroundService->saveResource($employee, ['component' => 'uc', 'type' => 'groups']);
        }

        return $userGroups;
    }

    /**
     * Saves the required user groups for a provider.
     *
     * @param array $provider   The provider to save the user groups for
     * @param array $userGroups The existing user groups of a provider
     *
     * @return array The now existing user groups of the provider
     */
    private function saveProviderUserGroups(array $provider, array $userGroups): array
    {
        $existingUserGroups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $provider['id']])])['hydra:member'];

        $userGroupCoordinator = $this->findUserGroupsByName($existingUserGroups, 'AANBIEDER_COORDINATOR');
        $userGroupMentor = $this->findUserGroupsByName($existingUserGroups, 'AANBIEDER_MENTOR');
        $userGroupVolunteer = $this->findUserGroupsByName($existingUserGroups, 'AANBIEDER_VOLUNTEER');

        $userGroups = $this->saveProviderCoordinatorUserGroup($provider, $userGroups, $userGroupCoordinator);
        $userGroups = $this->saveProviderMentorUserGroup($provider, $userGroups, $userGroupMentor);
        $userGroups = $this->saveProviderVolunteerUserGroup($provider, $userGroups, $userGroupVolunteer);

        return $userGroups;
    }

    /**
     * Saves a coordinator user group for a provider.
     *
     * @param array $provider             The provider to save the user group for
     * @param array $userGroups           The existing user groups of the provider
     * @param array $userGroupCoordinator The existing coordinator user group
     *
     * @return array The user groups that exist for the provider
     */
    private function saveProviderCoordinatorUserGroup(array $provider, array $userGroups, array $userGroupCoordinator): array
    {
        $coordinator = [
            'organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $provider['id']]),
            'name'         => 'AANBIEDER_COORDINATOR',
            'description'  => 'UserGroup coordinator of '.$provider['name'],
            'scopes'       => $this->getScopes('7ac8ff69-ed2f-42d6-9838-c1cdba19455d'),
        ];
        if ($userGroupCoordinator) {
            $userGroups[] = $this->commonGroundService->updateResource($coordinator, ['component' => 'uc', 'type' => 'groups', 'id' => $userGroupCoordinator['id']]);
        } else {
            $userGroups[] = $this->commonGroundService->saveResource($coordinator, ['component' => 'uc', 'type' => 'groups']);
        }

        return $userGroups;
    }

    /**
     * Saves a mentor user group for a provider.
     *
     * @param array $provider        The provider to save the user group for
     * @param array $userGroups      The existing user groups of the provider
     * @param array $userGroupMentor The existing mentor user group
     *
     * @return array The user groups that exist for the provider
     */
    private function saveProviderMentorUserGroup(array $provider, array $userGroups, array $userGroupMentor): array
    {
        $mentor = [
            'organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $provider['id']]),
            'name'         => 'AANBIEDER_MENTOR',
            'description'  => 'UserGroup mentor of '.$provider['name'],
            'scopes'       => $this->getScopes('6e6bf779-2505-4dff-be47-94a45d65f64e'),
        ];
        if ($userGroupMentor) {
            $userGroups[] = $this->commonGroundService->updateResource($mentor, ['component' => 'uc', 'type' => 'groups', 'id' => $userGroupMentor['id']]);
        } else {
            $userGroups[] = $this->commonGroundService->saveResource($mentor, ['component' => 'uc', 'type' => 'groups']);
        }

        return $userGroups;
    }

    /**
     * Saves a volunteer user group for a provider.
     *
     * @param array $provider           The provider to save the user group for
     * @param array $userGroups         The existing user groups of the provider
     * @param array $userGroupVolunteer The existing volunteer user group
     *
     * @return array
     */
    private function saveProviderVolunteerUserGroup(array $provider, array $userGroups, array $userGroupVolunteer): array
    {
        $volunteer = [
            'organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $provider['id']]),
            'name'         => 'AANBIEDER_VOLUNTEER',
            'description'  => 'UserGroup volunteer of '.$provider['name'],
            'scopes'       => $this->getScopes('a4c7de5b-be1b-4dda-9b37-62e5938ffe7b'),
        ];
        if ($userGroupVolunteer) {
            $userGroups[] = $this->commonGroundService->updateResource($volunteer, ['component' => 'uc', 'type' => 'groups', 'id' => $userGroupVolunteer['id']]);
        } else {
            $userGroups[] = $this->commonGroundService->saveResource($volunteer, ['component' => 'uc', 'type' => 'groups']);
        }

        return $userGroups;
    }
}
