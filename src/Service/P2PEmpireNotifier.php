<?php
declare(strict_types=1);

namespace App\Service;

use DateTime;
use JsonException;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\RedirectionExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\ServerExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\TransportExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Throwable;

final class P2PEmpireNotifier
{
    private const P2PEMPIRE_NEWS_ENDPOINT = 'https://p2pempire.com/en/newsfeed';

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
     */
    public function notify(): void
    {
        try {
            $news = $this->crawlNews();
            $filteredNews = $this->filterNews($news);
            $this->sendNews($filteredNews);
        } catch (Throwable $e) {
            $this->sentTelegramMessage($e->getMessage());
        }
    }

    /**
     * @throws TransportExceptionInterface
     * @throws ServerExceptionInterface
     * @throws RedirectionExceptionInterface
     * @throws ClientExceptionInterface
     * @throws JsonException
     */
    private function crawlNews(): array
    {
        $response = $this->client->request('GET', self::P2PEMPIRE_NEWS_ENDPOINT);
        $crawler = new Crawler($response->getContent());
        $data = $crawler->filter('script#__NEXT_DATA__')->text();

        return json_decode($data, true, 512, JSON_THROW_ON_ERROR)['props']['pageProps']['news'];
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
    private function sendNews(array $news): void
    {
        foreach ($news as $info) {
            $this->sentTelegramMessage(sprintf(
                self::TEMPLATE_TELEGRAM_MESSAGE_NEWS,
                $info['platformName'],
                $info['newsText'],
            ));
        }
    }

    /**
     * @throws TransportExceptionInterface
     */
    private function sentTelegramMessage(string $content): void
    {
        $endpoint = sprintf(self::TELEGRAM_SEND_MESSAGE_ENDPOINT, $this->p2pempireNewsBotTelegramSecretToken);
        $this->client->request('POST', $endpoint, [
            'body' => [
                'chat_id' => $this->myTelegramClientId,
                'parse_mode' => 'HTML',
                'text' => $content
            ]
        ]);
    }
}
