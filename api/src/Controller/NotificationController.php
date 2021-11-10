<?php


namespace App\Controller;


use App\Service\MailService;
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

        return new Response(json_encode(['username' =>$data['username']]), 200, ['Content-type' => 'application/json']);
    }
}