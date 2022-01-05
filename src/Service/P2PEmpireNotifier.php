<?php
declare(strict_types=1);

namespace App\Service;

use DateTime;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\DecodingExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

final class P2PEmpireNotifier
{
    private const P2PEMPIRE_NEWS_ENDPOINT = 'https://adminapi.p2pempire.com/api/NewsFeed/GetAllNews';

    private const TELEGRAM_SEND_MESSAGE_ENDPOINT = 'https://api.telegram.org/bot%s/sendMessage';

    private const TEMPLATE_TELEGRAM_MESSAGE_NEWS = <<<TELEGRAM
🗞️ <b>%s</b> 🗞️

%s
TELEGRAM;

    private const PAST_DAYS_TO_CHECK = '-2 days';

    private string $myTelegramClientId;

    private HttpClientInterface $client;

    private string $p2pempireNewsBotTelegramSecretToken;

    public function __construct(
        string $myTelegramClientId,
        string $p2pempireNewsBotTelegramSecretToken,
        HttpClientInterface $client
    ) {
        $this->myTelegramClientId = $myTelegramClientId;
        $this->p2pempireNewsBotTelegramSecretToken = $p2pempireNewsBotTelegramSecretToken;
        $this->client = $client;
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    public function notify(): void
    {
        $news = $this->crawlNews();
        $filteredNews = $this->filterNews($news);
        $this->sendTelegramMessages($filteredNews);
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws DecodingExceptionInterface
     * @throws ClientExceptionInterface
     */
    private function crawlNews(): array
    {
        $response = $this->client->request('POST', self::P2PEMPIRE_NEWS_ENDPOINT, [
            'json' => ['Id' => '-1', 'SelectedLanguage' => 'en'],
        ]);
        return $response->toArray();
    }

    private function filterNews(array $news): array
    {
        $dateFromToCheck = new DateTime(self::PAST_DAYS_TO_CHECK);
        return array_filter($news, static function (array $info) use ($dateFromToCheck) {
            $newsDate = new DateTime($info['createdDate']);
            return $newsDate > $dateFromToCheck;
        });
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sendTelegramMessages(array $news): void
    {
        $endpoint = sprintf(self::TELEGRAM_SEND_MESSAGE_ENDPOINT, $this->p2pempireNewsBotTelegramSecretToken);
        foreach ($news as $info) {
            $this->client->request('POST', $endpoint, [
                'body' => [
                    'chat_id' => $this->myTelegramClientId,
                    'parse_mode' => 'HTML',
                    'text' => sprintf(
                        self::TEMPLATE_TELEGRAM_MESSAGE_NEWS,
                        $info['platformName'],
                        $info['newsText'],
                    )
                ]
            ]);
        }
    }
}
