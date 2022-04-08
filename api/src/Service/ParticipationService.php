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
     * Updates and returns a participation with the correct status, depending on the participation type (providerOption).
     *
     * @param array $participation
     * @return array
     */
    public function setStatus(array $participation): array
    {
        if ($participation['providerOption'] == 'OTHER' && $participation['status'] == "REFERRED") {
            $participationUpdate = [
                'status'            => "ACTIVE",
                'learningNeed'      => $participation['learningNeed']['id'],
                'providerOption'    => $participation['providerOption']
            ];
            return $this->commonGroundService->updateResource($participationUpdate,
                ['component' => 'gateway', 'type' => 'participations', 'id' => $participation['id']]
            );
        }

        return $participation;
    }

    /**
     * Checks if the student.referred datetime of the given participation.learningNeed.student needs to be set, and if so does this and returns the update student.
     *
     * @param array $participation
     * @return array
     */
    public function checkStudentReferred(array $participation): array
    {
        $totalStudentParticipations = $this->commonGroundService->getResourceList(
            ['component' => 'gateway', 'type' => 'participations'],
            ['learningNeed.student.id' => $participation['learningNeed']['student']['id'], 'fields[]' => null],
            false
        )['total'];

        if ($totalStudentParticipations == 1) {
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

    /**
     * @TODO
     *
     * @return array
     */
    public function updateCompletedParticipations(): array
    {
        $result = [];
        $participations = $this->commonGroundService->getResourceList(
            ['component' => 'gateway', 'type' => 'participations'],
            ['status' => 'ACTIVE', 'fields[]' => ['status', 'learningNeed', 'providerOption']], // todo: add: end[from] 0:00 today end[till] 0:00 tomorrow
            false
        )['results'];

        foreach ($participations as $participation) {
            $participationUpdate = [
                'status'            => "COMPLETED",
                'learningNeed'      => $participation['learningNeed']['id'],
                'providerOption'    => $participation['providerOption']
            ];
            $result[] = $this->commonGroundService->updateResource($participationUpdate,
                ['component' => 'gateway', 'type' => 'participations', 'id' => $participation['id']]
            )['@uri'];
        }

        return $result;
    }
}
