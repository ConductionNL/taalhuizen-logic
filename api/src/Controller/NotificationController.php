<?php


namespace App\Controller;


use App\Service\MailService;
use App\Service\EmployeeService;
use App\Service\StudentService;
use App\Service\UserService;
use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Twig\Environment;

class NotificationController extends AbstractController
{
    private CommonGroundService $commonGroundService;

    public function __construct(CommonGroundService $commonGroundService)
    {
        $this->commonGroundService = $commonGroundService;
    }

    /**
     * @Route ("/users", methods={"POST"})
     */
    public function createUserAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag, Environment $twig)
    {
        $data = json_decode($request->getContent(), true);
        if ($data['action'] !== 'Create') {
            return new Response(json_encode(['username' => $data['resource']]), 200, ['Content-type' => 'application/json']);
        }
        $user = $commonGroundService->getResource($data['resource']);
        $mailService = new MailService($commonGroundService, $twig);
        $mailService->sendWelcomeMail($user, 'Welkom bij TOP', $parameterBag->get('frontendLocation'));

        return new Response(json_encode(['user' => $data['resource']]), 200, ['Content-type' => 'application/json']);
    }

    /**
     * @Route ("/organizations", methods={"POST"})
     */
    public function createOrEditOrganizationAction(Request $request, CommonGroundService $commonGroundService)
    {
        $data = json_decode($request->getContent(), true);
        $userService = new UserService($commonGroundService);
        if ($data['action'] === 'Create' || $data['action'] === 'Update') {
            // Create new userGroups in UC for this organization depending on organization type
            $organization = $commonGroundService->getResource($data['resource'], [], false);
            $userGroups = $userService->saveUserGroups($organization);
        } elseif ($data['action'] === 'Delete') {
            // Delete existing userGroups of this organization
            $userGroups = $userService->deleteUserGroups($data['resource']);
        }

        $result = ['organization' => $data['resource']];
        if ($data['action'] === 'Delete') {
            $result['action'] = $data['action'];
            $result['note'] = 'userGroups contains info of the Deleted userGroups';
        }
        $result['userGroups'] = $userGroups ?? 'Something went wrong';
        return new Response(json_encode($result), 200, ['Content-type' => 'application/json']);
    }

    /**
     * @Route ("/employees", methods={"POST"})
     */
    public function  createOrEditEmployeeAction(Request $request, CommonGroundService $commonGroundService)
    {
        $data = json_decode($request->getContent(), true);

        // if create or update
        if ($data['action'] === 'Create' || $data['action'] === 'Update') {
            // Retrieve employee object from gateway
            $employee = $this->commonGroundService->getResource(['component' => 'gateway', 'type' => 'employees', 'id' => $commonGroundService->getUuidFromUrl($data['resource'])], [], false);
            $employeeService = new EmployeeService($commonGroundService);
            $employee = $employeeService->checkOrganization($employee);
            // Create/update a user for it in the gateway with correct user groups
            $userService = new UserService($commonGroundService);
            $user = $userService->saveEmployeeUser($employee, $data['action']);
        } elseif ($data['action'] === 'Delete') {
            // Do nothing! This is already handled by the gateway. including deleting the user from UC
        }

        $result = [
            'employeeUri'    => $data['resource'],
            'user'           => $user ?? null,
            'employeeObject' => $employee ?? null
        ];

        return new Response(json_encode($result), 200, ['Content-type' => 'application/json']);
    }

    /**
     * @Route ("/students", methods={"POST"})
     */
    public function  createOrEditStudentAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag, Environment $twig)
    {
        $data = json_decode($request->getContent(), true);

        // if create or update
        if ($data['action'] === 'Create' || $data['action'] === 'Update') {
            // Retrieve student object from gateway
            $student = $this->commonGroundService->getResource(['component' => 'gateway', 'type' => 'students', 'id' => $commonGroundService->getUuidFromUrl($data['resource'])], [], false);
            // Check if we need to find a LanguageHouse with the students address
            $studentService = new StudentService($commonGroundService);
            $student = $studentService->checkLanguageHouse($student);
            // Create/update a user for it in the gateway
            $userService = new UserService($commonGroundService);
            $user = $userService->saveStudentUser($student, $data['action']);
        } elseif ($data['action'] === 'Delete') {
            // Do nothing! This is already handled by the gateway. including deleting the user from UC
        }

        $result = [
            'studentUri'   => $data['resource'],
            'user'      => $user ?? null,
            'studentObject'   => $student ?? null
        ];

        return new Response(json_encode($result), 200, ['Content-type' => 'application/json']);
    }

    /**
     * @Route ("/contact_moments", methods={"POST"})
     */
    public function  createContactMomentAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag, Environment $twig)
    {
        //TODO: test all of this
        $data = json_decode($request->getContent(), true);
        if ($data['action'] !== 'Create') {
            return new Response(json_encode(['username' => $data['resource']]), 200, ['Content-type' => 'application/json']);
        }

        //Find employee with the owner/user of this contactMoment and connect it to it TODO: move this code to a new service!?
        // Retrieve contactMoment object from gateway
        $contactMoment = $this->commonGroundService->getResource(['component' => 'gateway', 'type' => 'contact_moments', 'id' => $commonGroundService->getUuidFromUrl($data['resource'])], [], false);
        // Get owner to get the employee and connect it to this contactMoment
        // TODO: search through/in gateway instead of UC and MRC ?
        $user = $this->commonGroundService->getResource(['component' => 'uc', 'type' => 'users', 'id' => $contactMoment['@owner']], [], false);
        $employees = $this->commonGroundService->getResourceList(['component' => 'mrc', 'type' => 'employees'], ['person' => $user['person']], false);
        if (count($employees) > 0) {
            $employee = $employees[0];
            $updateContactMoment = [
                'employee' => $employee['id']
            ];
            $contactMoment = $this->commonGroundService->updateResource($updateContactMoment, ['component' => 'gateway', 'type' => 'contact_moments', 'id' => $commonGroundService->getUuidFromUrl($data['resource'])]);
        }

        $result = [
            'contactMomentUri'      => $data['resource'],
            'user'                  => $user['@id'] ?? $contactMoment['@owner'],
            'person'                => $user['person'],
            'employee'              => isset($employee) ? $employee['@id'] : null,
            'contactMomentObject'   => $contactMoment ?? null
        ];

        return new Response(json_encode($result), 200, ['Content-type' => 'application/json']);
    }
}
