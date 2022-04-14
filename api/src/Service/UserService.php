<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;
use GuzzleHttp\Exception\RequestException;
use Symfony\Component\Console\Style\SymfonyStyle;
use Twig\Environment;

class UserService
{
    private CommonGroundService $commonGroundService;
    private MailService $mailService;

    public function __construct(CommonGroundService $commonGroundService, MailService $mailService)
    {
        $this->commonGroundService = $commonGroundService;
        $this->mailService = $mailService;
    }

    /**
     * Gets the correct userGroup(s) of an organization and a given role
     *
     * @param array $organization
     * @param string $role
     * @return array
     */
    private function getUserGroups(array $organization, string $role): array
    {
        // get organization (type) of this employee, to make sure we only give/allow userGroups of the correct organization type
        $existingUserGroups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $organization['@uri']], false)['hydra:member'];
        $userGroups = [];

        switch ($organization['type']) {
            case 'taalhuis':
                $userGroups[] = $this->findUserGroupByName($existingUserGroups, 'TAALHUIS_'.$role)['id'];
                break;
            case 'aanbieder':
                if ($role == 'COORDINATOR_MENTOR') {
                    $userGroups[] = $this->findUserGroupByName($existingUserGroups, 'AANBIEDER_COORDINATOR')['id'];
                    $role = 'MENTOR';
                }
                $userGroups[] = $this->findUserGroupByName($existingUserGroups, 'AANBIEDER_'.$role)['id'];
                break;
            case 'bisc':
                $userGroups[] = '8e90f9f0-acb7-406d-9550-be614040effd'; // The bisc userGroupId todo: make this a helm value or something?
                break;
            case 'verwijzer':
            default:
                // TODO: ?
                break;
        }

        // TODO: maybe do something if we can't find any userGroups, because the given $role is incorrect in combination with the org type for example
        return $userGroups;
    }

    /**
     * Saves the user for an employee in the gateway and UC
     *
     * @param array $employee
     * @param string $action
     * @return array
     */
    public function saveEmployeeUser(array $employee, string $action): array
    {
        // create (or update) user for this employee with the person connection and correct userGroups (switch employee role)
        $user = [
            "locale" => "nl",
            "username" => $employee['person']['emails'][0]['email'],
            "organization" => $employee['organization']['id'],
            "userGroups" => $this->getUserGroups($employee['organization'], $employee['role']),
            "person" => $employee['person']['id']
        ];

        if ($action == 'Create') {
            $existingUser = $this->employeeUserExistsWithUsername($user, $employee);
            if (!is_null($existingUser)) {
                return $existingUser;
            }
        }

        return $this->saveUser($user, $employee['person'], $action);
    }

    /**
     * Checks if a (employee) user exists with the given email, and if so handle accordingly.
     *
     * @param array $user
     * @return array|null
     */
    private function employeeUserExistsWithUsername(array $user, array $employee): ?array
    {
        //Check if a user exists with $user['username'] email.
        $existingUsers = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['username' => urlencode($user['username'])], false)['hydra:member'];
        if (count($existingUsers) > 0) {
            $existingUser = $existingUsers[0];
            //If a user exists with the email, check if an employee already exists with this user.
            $existingEmployees = $this->commonGroundService->getResourceList(['component' => 'gateway', 'type' => 'employees'], ['person._uri' => $existingUser['person']], false)['results']; // add to query?: 'person.user.username' => $existingUser['username'] OR: 'person.emails.email' => $existingUser['username']
            if (count($existingEmployees) > 0) {
                //If an employee exists with this user, delete new employee and send email to the existing user.
                $this->commonGroundService->deleteResource(null, ['component' => 'gateway', 'type' => 'employees', 'id' => $employee['id']]);
                $this->mailService->sendEmployeeExistsMail($existingUser, 'Iemand heeft geprobeerd een medewerker toe te voegen met uw email');

                $user['message'] = "Warning: There already exists an user with this email and an employee with this user['person']. Deleted new created employee and send warning email.";
                $employee = $existingEmployees[0];
            } else {
                //If no employee exists with this user, (connect new employee to the user, done by: ) update user with correct data (person id!) & send reset-password/welcome email.
                $user = $this->commonGroundService->updateResource($user, ['component' => 'gateway', 'type' => 'users', 'id' => $existingUser['id']]);
                $this->mailService->sendWelcomeMail($this->commonGroundService->getResource($user['@uri']), 'Welkom bij TOP');
                $employee = $this->commonGroundService->getResource(['component' => 'gateway', 'type' => 'employees', 'id' => $employee['id']], [], false);

                $user['message'] = "Warning: There already exists an user with this email, connected new employee to this existing user.";
            }
        } else {
            //If no user exists with this email, return null.
            return null;
        }

        $user['employee'] = $employee;
        return $user;
    }

    /**
     * Saves a user in the gateway and UC
     *
     * @param array $user
     * @param array $person
     * @param string $action
     * @return array|string[]
     */
    private function saveUser(array $user, array $person, string $action): array
    {
        $existingUsers = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users'], ['person' => $person['@uri']], false)['hydra:member'];
        if (count($existingUsers) > 0) {
            $existingUser = $existingUsers[0];
        }

        if ($action == 'Create') {
            if (isset($existingUser)) {
                return ['Error' => 'There already exists a user for this person: ' . $person['@uri']];
            }
            // Temp password
            $user['password'] = $this->randomPassword();
            // Save user in the gateway (This will send a mail for a new user to change their password)
            $user = $this->commonGroundService->createResource($user, ['component' => 'gateway', 'type' => 'users']);
        } elseif ($action == 'Update') {
            if (!isset($existingUser)) {
                // TODO: maybe create a new user, even though we are doing an Update and not a Create ?
                return ['Error' => 'Couldn\'t find a user for this person: ' . $person['@uri']];
            }
            if ($person['emails'][0]['email'] != $existingUser['username']) {
                $user['currentPassword'] = '???'; // TODO!!!
                return ['Error' => 'Changing a username is not implemented yet, we need a password for that'];
            }
            // Update user in the gateway (and UC because of that)
            $user = $this->commonGroundService->updateResource($user, ['component' => 'gateway', 'type' => 'users', 'id' => $existingUser['id']]);
        }

        return $user;
    }

    /**
     * Generates a random (temp) password
     *
     * @return string
     */
    private function randomPassword() {
        $alphabet = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ1234567890?></.,\\|\'";:]}[{=+-_)(*&^%$#@!`~';
        $pass = array(); //remember to declare $pass as an array
        $alphaLength = strlen($alphabet) - 1; //put the length -1 in cache
        for ($i = 0; $i < 12; $i++) {
            $n = rand(0, $alphaLength);
            $pass[] = $alphabet[$n];
        }
        return implode($pass); //turn the array into a string
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
     * Deletes the user groups for an organization.
     *
     * @param string $organization The organization to delete user groups for
     *
     * @return false|mixed False if failed, array of userGroups if successful
     */
    public function deleteUserGroups(string $organization)
    {
        $userGroups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $organization], false)['hydra:member'];
        if ($userGroups > 0) {
            foreach ($userGroups as $userGroup) {
                $this->commonGroundService->deleteResource(null, ['component'=>'uc', 'type' => 'groups', 'id' => $userGroup['id']]);
            }
        }
        return $userGroups;
    }

    /**
     * Finds user groups in an array of user groups by their name.
     *
     * @param array  $userGroups The user group array
     * @param string $name       The name to look for
     *
     * @return array The existing user group (if it exists)
     */
    private function findUserGroupByName(array $userGroups, string $name): ?array
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
        $scopes = $this->commonGroundService->getResource(['component' => 'uc', 'type' => 'groups', 'id' => $userGroupId], [], false)['scopes'];
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
        $existingUserGroups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $languageHouse['id']])], false)['hydra:member'];
        $userGroupCoordinator = $this->findUserGroupByName($existingUserGroups, 'TAALHUIS_COORDINATOR');
        $userGroupEmployee = $this->findUserGroupByName($existingUserGroups, 'TAALHUIS_EMPLOYEE');

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
        $existingUserGroups = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['organization' => $this->commonGroundService->cleanUrl(['component' => 'cc', 'type' => 'organizations', 'id' => $provider['id']])], false)['hydra:member'];

        $userGroupCoordinator = $this->findUserGroupByName($existingUserGroups, 'AANBIEDER_COORDINATOR');
        $userGroupMentor = $this->findUserGroupByName($existingUserGroups, 'AANBIEDER_MENTOR');
        $userGroupVolunteer = $this->findUserGroupByName($existingUserGroups, 'AANBIEDER_VOLUNTEER');

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

    /**
     * Retrieves all user groups for a certain code (name)
     * @param string $code The code(name) for the user groups
     * @return array the user groups
     */
    private function getAllUserGroupsOfType (string $code): array
    {
        return $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'groups'], ['name' => $code, 'limit' => 1000], false)['hydra:member'];
    }

    /**
     * Splits out all scope ids from a usergroup
     * @param array $userGroup The user group to get the scopes for
     * @return array An array of scope ids in the user group
     */
    private function getAllScopeIdsForUserGroup (array $userGroup): array
    {
        $scopeIds = [];
        foreach($userGroup['scopes'] as $scope)
        {
            $scopeIds[] = "/scopes/{$scope['id']}";
        }
        return $scopeIds;
    }

    private function addScopesToUserGroup(array $currentScopes, array $scopesToAdd): array
    {
        foreach($scopesToAdd as $scope)
        {
            if(!in_array($scope, $currentScopes)){
                $currentScopes[] = $scope;
            }
        }
        return $currentScopes;
    }

    private function removeScopesFromUserGroup(array $currentScopes, array $scopesToRemove): array
    {
        foreach($currentScopes as $key=>$scope){
            if(in_array($scope, $scopesToRemove)){
                unset($currentScopes[$key]);
            }
        }
        return array_values($currentScopes);
    }

    public function mutateScopes(string $directive, string $code, array $scopes, SymfonyStyle $io): int
    {
        $errorCount = 0;
        $userGroups = $this->getAllUserGroupsOfType($code);

        $io->progressStart(count($userGroups));
        foreach($userGroups as $userGroup){
            $io->text("");
            $io->section("Checking userGroup {$userGroup['id']}");
            $currentScopes = $this->getAllScopeIdsForUserGroup($userGroup);
            switch($directive)
            {
                case 'add':
                    $userGroup['scopes'] = $this->addScopesToUserGroup($currentScopes, $scopes);
                    break;
                case 'remove':
                    $userGroup['scopes'] = $this->removeScopesFromUserGroup($currentScopes, $scopes);
                    break;
            }
            try{
                unset($userGroup['users']);
                unset($userGroup['children']);
                $this->commonGroundService->updateResource($userGroup, ['component' => 'uc', 'type' => 'groups', 'id' => $userGroup['id']]);
            } catch(RequestException $exception){
                $io->error($exception->getMessage());
                $errorCount++;
            }
            $io->text('UserGroup had '.count($currentScopes).' scopes, now has '.count($userGroup['scopes']).' scopes. '.(count($userGroup['scopes'])-count($currentScopes)).' scopes have been added');
            $io->progressAdvance();
        }
        $io->progressFinish();
        return round($errorCount/count($userGroups)*100) == 0 && $errorCount > 0 ? 1 : round($errorCount/count($userGroups)*100);
    }
}
