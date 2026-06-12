<?php

namespace Customize\EventSubscriber;

use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\KernelEvents;

class DemoSiteSubscriber implements EventSubscriberInterface
{
    private const DEMO_NOTICE = 'このサイトはポートフォリオ用デモです。実際の購入・決済は行われません。';

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::REQUEST => ['onKernelRequest', -64],
            KernelEvents::RESPONSE => ['onKernelResponse', -64],
        ];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        if ($request->isMethod('POST') && $this->isCheckoutRequest($request)) {
            $event->setResponse($this->createDemoCheckoutResponse());
        }
    }

    public function onKernelResponse(ResponseEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $response = $event->getResponse();

        if (!$this->shouldDecorate($request, $response)) {
            return;
        }

        $content = $response->getContent();
        if (!is_string($content) || $content === '') {
            return;
        }

        $content = $this->addGlobalDemoBanner($content);

        if ($this->isConfirmPage($request)) {
            $content = $this->addCheckoutWarning($content);
        }

        $response->setContent($content);
    }

    private function shouldDecorate(Request $request, Response $response): bool
    {
        if (!$response->isSuccessful() || $this->isAdminRequest($request)) {
            return false;
        }

        $contentType = strtolower((string) $response->headers->get('Content-Type'));

        return $contentType === '' || str_contains($contentType, 'text/html');
    }

    private function isAdminRequest(Request $request): bool
    {
        $route = (string) $request->attributes->get('_route', '');
        $path = $request->getPathInfo();

        return str_starts_with($route, 'admin') || preg_match('#^/(index\.php/)?admin(/|$)#', $path) === 1;
    }

    private function isConfirmPage(Request $request): bool
    {
        return $request->attributes->get('_route') === 'shopping_confirm'
            || $request->getPathInfo() === '/shopping/confirm';
    }

    private function isCheckoutRequest(Request $request): bool
    {
        return $request->attributes->get('_route') === 'shopping_checkout'
            || $request->getPathInfo() === '/shopping/checkout';
    }

    private function addGlobalDemoBanner(string $content): string
    {
        if (str_contains($content, 'ec-demo-global-notice')) {
            return $content;
        }

        $style = $this->createDemoStyle();
        $banner = '<div class="ec-demo-global-notice" role="note">'
            . '<strong>ポートフォリオ用デモサイト</strong>'
            . '<span>実際の購入・決済はできません。本物の個人情報やカード情報は入力しないでください。</span>'
            . '</div>';

        if (stripos($content, '</head>') !== false) {
            $content = str_ireplace('</head>', $style.'</head>', $content);
        } else {
            $banner = $style.$banner;
        }

        if (preg_match('/<body\b[^>]*>/i', $content, $matches, PREG_OFFSET_CAPTURE) === 1) {
            $position = $matches[0][1] + strlen($matches[0][0]);

            return substr_replace($content, $banner, $position, 0);
        }

        return $banner.$content;
    }

    private function addCheckoutWarning(string $content): string
    {
        if (str_contains($content, 'ec-demo-checkout-warning')) {
            return $content;
        }

        $notice = '<div class="ec-demo-checkout-warning" role="alert">'
            . '最終確定ボタンを押しても、実際の注文・決済は行われません。'
            . 'このサイトはポートフォリオ確認用のデモです。'
            . '</div>';

        $patterns = [
            '/(<button\b[^>]*type=(["\'])submit\2[^>]*>)/i',
            '/(<input\b[^>]*type=(["\'])submit\2[^>]*>)/i',
            '/(<\/form>)/i',
        ];

        foreach ($patterns as $pattern) {
            $updated = preg_replace($pattern, $notice.'$1', $content, 1, $count);
            if ($count > 0 && is_string($updated)) {
                return $updated;
            }
        }

        return $content.$notice;
    }

    private function createDemoCheckoutResponse(): Response
    {
        $html = '<!doctype html>'
            . '<html lang="ja">'
            . '<head>'
            . '<meta charset="UTF-8">'
            . '<meta name="viewport" content="width=device-width, initial-scale=1">'
            . '<title>デモ注文の確認</title>'
            . $this->createDemoStyle()
            . '</head>'
            . '<body class="ec-demo-complete-body">'
            . '<main class="ec-demo-complete" role="main">'
            . '<p class="ec-demo-complete__label">ポートフォリオ用デモサイト</p>'
            . '<h1>実際の購入は行われません</h1>'
            . '<p>'.self::DEMO_NOTICE.'</p>'
            . '<p>Amazon Payやクレジットカードなどの本番決済には接続していません。注文データの確定、決済、発送は行われません。</p>'
            . '<div class="ec-demo-complete__actions">'
            . '<a href="/">トップページへ戻る</a>'
            . '<a href="/cart">カートへ戻る</a>'
            . '</div>'
            . '</main>'
            . '</body>'
            . '</html>';

        return new Response($html, Response::HTTP_OK, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function createDemoStyle(): string
    {
        return '<style>'
            . '.ec-demo-global-notice{position:sticky;top:0;z-index:10000;display:flex;gap:.75rem;justify-content:center;align-items:center;flex-wrap:wrap;padding:10px 16px;background:#fff3cd;border-bottom:1px solid #d39e00;color:#4d3900;font-size:14px;line-height:1.6;text-align:center}'
            . '.ec-demo-global-notice strong{font-weight:700}'
            . '.ec-demo-checkout-warning{margin:18px 0;padding:14px 16px;border:2px solid #d9480f;border-radius:4px;background:#fff4e6;color:#7c2d12;font-weight:700;line-height:1.7}'
            . '.ec-demo-complete-body{margin:0;background:#f7f7f7;color:#222;font-family:-apple-system,BlinkMacSystemFont,"Segoe UI",sans-serif}'
            . '.ec-demo-complete{box-sizing:border-box;width:min(720px,calc(100% - 32px));margin:72px auto;padding:32px;border:1px solid #ddd;border-radius:6px;background:#fff;line-height:1.8}'
            . '.ec-demo-complete h1{margin:0 0 16px;font-size:28px;line-height:1.4}'
            . '.ec-demo-complete__label{display:inline-block;margin:0 0 16px;padding:4px 10px;background:#fff3cd;border:1px solid #d39e00;border-radius:4px;color:#4d3900;font-weight:700}'
            . '.ec-demo-complete__actions{display:flex;gap:12px;flex-wrap:wrap;margin-top:24px}'
            . '.ec-demo-complete__actions a{display:inline-block;padding:10px 16px;border:1px solid #333;border-radius:4px;color:#222;text-decoration:none}'
            . '@media(max-width:600px){.ec-demo-complete{margin:32px auto;padding:24px}.ec-demo-complete h1{font-size:24px}}'
            . '</style>';
    }
}
