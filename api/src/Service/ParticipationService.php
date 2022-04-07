<?php


namespace App\Service;


use Conduction\CommonGroundBundle\Service\CommonGroundService;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ParticipationService
{
    private CommonGroundService $commonGroundService;
    private ParameterBagInterface $parameterBag;

    public function __construct(CommonGroundService $commonGroundService, ParameterBagInterface $parameterBag)
    {
        $this->commonGroundService = $commonGroundService;
        $this->parameterBag = $parameterBag;
    }

    /**
     * Returns a participation body with the correct updated status, depending on the participation type (providerOption)
     *
     * @param array $participation
     * @return array
     */
    public function setStatus(array $participation): array
    {
        if ($participation['providerOption'] == 'OTHER' && $participation['status'] == "REFERRED") {
            $participationUpdate['status'] = "ACTIVE";
            $participationUpdate['learningNeed'] = $participation['learningNeed']['id'];
            $participationUpdate['providerOption'] = $participation['providerOption'];
            return $this->commonGroundService->updateResource($participationUpdate, ['component' => 'gateway', 'type' => 'participations', 'id' => $participation['id']]);
        }
        return [];
    }
}
