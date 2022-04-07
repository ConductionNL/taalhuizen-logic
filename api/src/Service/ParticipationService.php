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
            return $this->commonGroundService->updateResource($participationUpdate,
                ['component' => 'gateway', 'type' => 'participations', 'id' => $participation['id']]
            );
        }

        return [];
    }

    public function checkStudentReferred(array $participation): array
    {
        $totalStudentParticipations = $this->commonGroundService->getResourceList(
            ['component' => 'gateway', 'type' => 'participations'],
            ['learningNeed.student.id' => $participation['learningNeed']['student']['id'], 'fields[]' => null]
        )['total'];

        if ($totalStudentParticipations == 0) {
            $studentUpdate = [
                'person'    => $this->commonGroundService->getUuidFromUrl($participation['learningNeed']['student']['person']['@id']), // we don't have 'id' here, maxDepth...
                'referred'  => $participation['@dateCreated']
            ];

            return $this->commonGroundService->updateResource($studentUpdate,
                ['component' => 'gateway', 'type' => 'students', 'id' => $participation['learningNeed']['student']['id']]
            );
        }

        return [];
    }
}
