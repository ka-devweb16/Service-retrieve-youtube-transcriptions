<?php

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * @author Kev
 */
final class YoutubeService
{
    private const YOUTUBE_TRANSCRIPT_ENDPOINT = 'https://www.youtube.com/youtubei/v1/get_transcript?key=AIzaSyAO_FJ2SlqU8Q4STEHLGCilw_Y9_11qcW8';

    public function __construct(private readonly HttpClientInterface $httpClient)
    {}

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function getSentencesFromYoutubeTranscription(string $youtubeVideoID): array
    {
        /*
            Note: The param contains specials characters like:
                => <Device Control Two> (DC2),
                => <Start of Heading> (SOH),
                => <Cancel> (CAN),
                => <Line Tabulation> (VT)
            Do not modify the string... otherwise the base64 string will change!
        */
        $param = base64_encode(
            string: "
{$youtubeVideoID}CgNhc3ISAmZyGgA%3D*3engagement-panel-searchable-transcript-search-panel0"
        );

        $response = $this
                    ->httpClient
                    ->request(
                        method: Request::METHOD_POST,
                        url: self::YOUTUBE_TRANSCRIPT_ENDPOINT,
                        options: [
                            'json' => [
                                'context' => [
                                    'client' => [
                                        'clientName' => 'WEB',
                                        'clientVersion' => '2.20220815.01.00',
                                    ],
                                ],
                                'params' => $param
                            ]
                        ]
                    );

        $data = $response->toArray();

        $initialSegments = $data['actions'][0]['updateEngagementPanelAction']['content']['transcriptRenderer']['content']['transcriptSearchPanelRenderer']['body']['transcriptSegmentListRenderer']['initialSegments'] ?? [];

        if (empty($initialSegments) === true) {
            return [];
        }

        $sentences = [];

        foreach ($initialSegments as $data) {
            $sentence = $data['transcriptSegmentRenderer']['snippet']['runs'][0]['text'] ?? null;

            if ($sentence === null) {
                continue;
            }

            $sentences[] = trim(
                string: preg_replace(
                    pattern: '/\s+/',
                    replacement: ' ',
                    subject: $sentence
                )
            );
        }

        return $sentences;
    }
}
