<?php


namespace App\Controller;


use App\Service\MailService;
use App\Service\UserGroupService;
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
    public function createOrEditOrganizationAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag, Environment $twig)
    {
        $data = json_decode($request->getContent(), true);
        $userGroupService = new UserGroupService($commonGroundService);
        if ($data['action'] === 'Create' || $data['action'] === 'Update') {
            // Create new userGroups in UC for this organization depending on organization type
            $organization = $commonGroundService->getResource($data['resource'], [], false);
            $userGroups = $userGroupService->saveUserGroups($organization);
        } elseif ($data['action'] === 'Delete') {
            // Delete existing userGroups of this organization
            $userGroups = $userGroupService->deleteUserGroups($data['resource']);
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
    public function  createOrEditEmployeeAction(Request $request)
    {
        $data = json_decode($request->getContent(), true);

        // retrieve employee object from gateway
        $employee = $this->commonGroundService->getResource(['component' => 'gateway', 'type' => 'employees', 'id' => substr($data['resource'], strrpos($data['resource'], '/') + 1)]);

        var_dump($employee);
        die;

        // if create or update
        // get employee, get the person from this employee
        // create (or update) user for this employee with the person connection and correct userGroups (switch employee role)
        // (send mail if needed)

        // if delete
        // delete the user
        $result = [];

        return new Response(json_encode($result), 200, ['Content-type' => 'application/json']);
    }

    /**
     * @Route ("/students", methods={"POST"})
     */
    public function  createOrEditStudentAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag, Environment $twig)
    {
        //TODO: see employee above this function

        $data = json_decode($request->getContent(), true);

        // if create or update
        // get student, get the person from this student
        // create (or update) user for this student with the person connection
        // (send mail if needed)

        // if delete
        // delete the user

        $result = [];

        return new Response(json_encode($result), 200, ['Content-type' => 'application/json']);
    }
}
