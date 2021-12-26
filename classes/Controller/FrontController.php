<?php

/**
 * FrontController class.
 */

namespace Alltube\Controller;

use Alltube\Library\Exception\PasswordException;
use Alltube\Library\Exception\AlltubeLibraryException;
use Alltube\Library\Exception\WrongPasswordException;
use Alltube\Locale;
use Alltube\Middleware\CspMiddleware;
use Exception;
use Slim\Http\StatusCode;
use Symfony\Component\ErrorHandler\ErrorRenderer\HtmlErrorRenderer;
use Throwable;
use Psr\Container\ContainerInterface;
use Slim\Http\Request;
use Slim\Http\Response;
use Slim\Views\Smarty;

/**
 * Main controller.
 */
class FrontController extends BaseController
{
    /**
     * Smarty view.
     *
     * @var Smarty
     */
    private $view;

    /**
     * BaseController constructor.
     *
     * @param ContainerInterface $container Slim dependency container
     */
    public function __construct(ContainerInterface $container)
    {
        parent::__construct($container);

        $this->view = $this->container->get('view');
    }

    /**
     * Display index page.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function index(Request $request, Response $response): Response
    {
        $this->view->render(
            $response,
            'index.tpl',
            [
                'class' => 'index',
                'description' => $this->localeManager->t(
                    'Easily download videos from YouTube, Dailymotion, Vimeo and other websites.'
                ),
                'supportedLocales' => $this->localeManager->getSupportedLocales(),
            ]
        );

        return $response;
    }

    /**
     * Switch locale.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param string[] $data Query parameters
     *
     * @return Response
     */
    public function locale(Request $request, Response $response, array $data): Response
    {
        $this->localeManager->setLocale(new Locale($data['locale']));

        return $response->withRedirect($this->router->pathFor('index'));
    }

    /**
     * Display a list of extractors.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     * @throws AlltubeLibraryException
     */
    public function extractors(Request $request, Response $response): Response
    {
        $this->view->render(
            $response,
            'extractors.tpl',
            [
                'extractors' => $this->downloader->getExtractors(),
                'class' => 'extractors',
                'title' => $this->localeManager->t('Supported websites'),
                'description' => $this->localeManager->t('List of all supported websites from which CyberStainBot ' .
                    'can extract video or audio files'),
            ]
        );

        return $response;
    }

    /**
     * Display a password prompt.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     */
    public function password(Request $request, Response $response): Response
    {
        $this->view->render(
            $response,
            'password.tpl',
            [
                'class' => 'password',
                'title' => $this->localeManager->t('Password prompt'),
                'description' => $this->localeManager->t(
                    'You need a password in order to download this video with CyberStainBot'
                ),
            ]
        );

        return $response->withStatus(StatusCode::HTTP_FORBIDDEN);
    }

    /**
     * Return the video description page.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     * @throws AlltubeLibraryException
     */
    private function getInfoResponse(Request $request, Response $response)
    {
        try {
            $this->video->getJson();
        } catch (PasswordException $e) {
            return $this->password($request, $response);
        } catch (WrongPasswordException $e) {
            return $this->displayError($request, $response, $this->localeManager->t('Wrong password'));
        }

        if (isset($this->video->entries)) {
            $template = 'playlist.tpl';
        } else {
            $template = 'info.tpl';
        }
        $title = $this->localeManager->t('Video download');
        $description = $this->localeManager->t(
            'Download video from @extractor',
            ['@extractor' => $this->video->extractor_key]
        );
        if (isset($this->video->title)) {
            $title = $this->video->title;
            $description = $this->localeManager->t(
                'Download @title from @extractor',
                [
                    '@title' => $this->video->title,
                    '@extractor' => $this->video->extractor_key
                ]
            );
        }
        $this->view->render(
            $response,
            $template,
            [
                'video' => $this->video,
                'class' => 'info',
                'title' => $title,
                'description' => $description,
                'defaultFormat' => $this->defaultFormat,
            ]
        );

        return $response;
    }

    /**
     * Dislay information about the video.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     *
     * @return Response HTTP response
     * @throws AlltubeLibraryException
     */
    public function info(Request $request, Response $response): Response
    {
        $url = $request->getQueryParam('url') ?: $request->getQueryParam('v');

        if (isset($url) && !empty($url)) {
            $this->video = $this->downloader->getVideo($url, $this->getFormat($request), $this->getPassword($request));

            if ($this->config->convert && $request->getQueryParam('audio')) {
                // We skip the info page and get directly to the download.
                return $response->withRedirect(
                    $this->router->pathFor('download', [], $request->getQueryParams())
                );
            } else {
                return $this->getInfoResponse($request, $response);
            }
        } else {
            return $response->withRedirect($this->router->pathFor('index'));
        }
    }

    /**
     * Display an user-friendly error.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param string $message Error message
     *
     * @return Response HTTP response
     */
    protected function displayError(Request $request, Response $response, string $message): Response
    {
        $this->view->render(
            $response,
            'error.tpl',
            [
                'error' => $message,
                'class' => 'video',
                'title' => $this->localeManager->t('Error'),
            ]
        );

        return $response->withStatus(StatusCode::HTTP_INTERNAL_SERVER_ERROR);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function notFound(Request $request, Response $response): Response
    {
        return $this->displayError($request, $response, $this->localeManager->t('Page not found'))
            ->withStatus(StatusCode::HTTP_NOT_FOUND);
    }

    /**
     * @param Request $request
     * @param Response $response
     * @return Response
     */
    public function notAllowed(Request $request, Response $response): Response
    {
        return $this->displayError($request, $response, $this->localeManager->t('Method not allowed'))
            ->withStatus(StatusCode::HTTP_METHOD_NOT_ALLOWED);
    }

    /**
     * Display an error page.
     *
     * @param Request $request PSR-7 request
     * @param Response $response PSR-7 response
     * @param Throwable $error Error to display
     *
     * @return Response HTTP response
     */
    public function error(Request $request, Response $response, Throwable $error): Response
    {
        $this->logger->error($error);

        // We apply the CSP manually because middlewares are not called on error pages.
        $cspMiddleware = new CspMiddleware($this->container);

        /** @var Response $response */
        $response = $cspMiddleware->applyHeader($response);

        if ($this->config->debug) {
            $renderer = new HtmlErrorRenderer(true, null, null, $this->container->get('root_path'));
            $exception = $renderer->render($error);

            $response->getBody()->write($exception->getAsString());
            foreach ($exception->getHeaders() as $header => $value) {
                $response = $response->withHeader($header, $value);
            }

            return $response->withStatus($exception->getStatusCode());
        } else {
            if ($error instanceof Exception) {
                $message = $error->getMessage();
            } else {
                $message = '';
            }

            return $this->displayError($request, $response, $message);
        }
    }
}
