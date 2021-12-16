<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Twig\Environment;

class MailService
{
    private CommonGroundService $commonGroundService;
    private Environment $twig;

    public function __construct(CommonGroundService $commonGroundService, Environment $twig)
    {
        $this->commonGroundService = $commonGroundService;
        $this->twig = $twig;
    }

    public function sendWelcomeMail(array $user, string $subject, string $frontend): bool
    {
        $response = $this->commonGroundService->getResourceList(['component' => 'uc', 'type' => 'users', 'id' => "{$user['id']}/token"], ['type' => 'SET_PASSWORD']);
        $person = $this->commonGroundService->isResource($user['person']);

        $service = $this->commonGroundService->getResourceList(['component' => 'bs', 'type' => 'services'])['hydra:member'][0];
        $parameters = [
            'fullname'              => $person['name'] ?? $user['username'],
            'base64_encoded_email'  => base64_encode($user['username']),
            'base64_encoded_token'  => base64_encode($response['token']),
            'app_base_url'          => rtrim($frontend, '/'),
            'subject'               => $subject,
        ];

        $content = $this->twig->render('welcome-e-mail.html.twig', $parameters);

        $message = $this->commonGroundService->createResource(
            [
                'reciever' => $user['username'],
                'sender'   => 'taalhuizen@biscutrecht.nl',
                'content'  => $content,
                'type'     => 'email',
                'status'   => 'queued',
                'service'  => '/services/' . $service['id'],
                'subject'  => $subject,
            ],
            ['component' => 'bs', 'type' => 'messages']
        );

        return true;
    }

    public function sendShareStudentMail(array $shareStudent, string $subject): bool
    {
        $service = $this->commonGroundService->getResourceList(['component' => 'bs', 'type' => 'services'])['hydra:member'][0];
        $parameters = [
            'fullname' => $shareStudent['email'],
            'studentName' => $shareStudent['student']['person']['name'],
            'subject'   => $subject,
        ];

        $content = $this->twig->render('share-student-e-mail.html.twig', $parameters);

        $message = $this->commonGroundService->createResource(
            [
                'reciever' => $shareStudent['email'],
                'sender'   => 'taalhuizen@biscutrecht.nl',
                'content'  => $content,
                'type'     => 'email',
                'status'   => 'queued',
                'service'  => '/services/' . $service['id'],
                'subject'  => $subject,
            ],
            ['component' => 'bs', 'type' => 'messages']
        );

        return true;
    }
}
