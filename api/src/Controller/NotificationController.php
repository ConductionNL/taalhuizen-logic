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
    /**
     * @Route ("/users", methods={"POST"})
     */
    public function createUserAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag, Environment $twig)
    {
        $data = json_decode($request->getContent(), true);
        if($data['action'] !== 'Create'){
            return new Response(json_encode(['username' =>$data['resource']]), 200, ['Content-type' => 'application/json']);
        }
        $user = $commonGroundService->getResource($data['resource']);
        $mailService = new MailService($commonGroundService, $twig);
        $mailService->sendWelcomeMail($user, 'Welkom bij TOP', $parameterBag->get('frontendLocation'));

        return new Response(json_encode(['user' =>$data['resource']]), 200, ['Content-type' => 'application/json']);
    }

    /**
     * @Route ("/organizations", methods={"POST"})
     */
    public function createOrganizationAction(Request $request, CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag, Environment $twig)
    {
        $data = json_decode($request->getContent(), true);
        $organization = $commonGroundService->getResource($data['resource']);
        $userGroupService = new UserGroupService($commonGroundService);
        if ($data['action'] === 'Create' || $data['action'] === 'Update') {
            // Create new userGroups in UC for this organization depending on organization type
            $userGroups = $userGroupService->saveUserGroups($organization);
        } elseif ($data['action'] === 'Delete') {
            // Delete existing userGroups of this organization depending on organization type
            $userGroups = $userGroupService->deleteUserGroups($organization);
        }

        $result = ['organization' => $data['resource']];
        if ($data['action'] === 'Delete') {
            $result['action'] = $data['action'];
            $result['note'] = 'userGroups contains info of the Deleted userGroups';
        }
        $result['userGroups'] = $userGroups ?? 'Something went wrong';
        return new Response(json_encode($result), 200, ['Content-type' => 'application/json']);
    }
}
