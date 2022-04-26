<?php
/**
 *
 * @package Error Pages
 * @copyright (c) 2014 ForumHulp.com
 *            (c) 2022 DSR!
 * @license http://opensource.org/licenses/gpl-2.0.php GNU General Public License v2
 *
 */

namespace dsr\errorpages\event;

use phpbb\config\config;
use phpbb\language\language;
use phpbb\log\log;
use phpbb\template\template;
use phpbb\user;
use Symfony\Component\HttpKernel\Event\GetResponseForExceptionEvent;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class listener implements EventSubscriberInterface
{
    protected $config;
    protected $user;
    protected $template;
    protected $log;
    protected $lang;

    public function __construct(
        config   $config,
        user     $user,
        template $template,
        log      $log,
        language $lang
    ) {
        $this->config = $config;
        $this->user = $user;
        $this->template = $template;
        $this->log = $log;
        $this->lang = $lang;
    }

    public static function getSubscribedEvents()
    {
        return array(
            KernelEvents::EXCEPTION => array('onKernelException', 2000)
        );
    }

    public function onKernelException(GetResponseForExceptionEvent $event)
    {
        $this->lang->add_lang('info_acp_errorpages', 'dsr/errorpages');

        // Get the exception object from the received event
        $exception = $event->getException();
        $request = $event->getRequest();
        $status_code = $exception instanceof HttpExceptionInterface ? $exception->getStatusCode() : 500;

        // error process
        $error_messages_list = [
            '400' => 'ERROR_BAD_REQUEST',
            '401' => 'ERROR_AUTH_REQUIRED',
            '403' => 'ERROR_FORBIDDEN',
            '404' => 'ERROR_NOT_FOUND',
            '405' => 'METHOD_NOT_ALLOWED',
            '406' => 'NOT_ACCEPTABLE',
            '407' => 'PROXY_AUTHENTICATION_REQUIRED',
            '408' => 'REQUEST_TIMED_OUT',
            '409' => 'CONFLICTING_REQUEST',
            '410' => 'GONE',
            '411' => 'CONTENT_LENGTH_REQUIRED',
            '412' => 'PRECONDITION_FAILED',
            '413' => 'REQUEST_ENTITY_TOO_LONG',
            '414' => 'REQUEST_URI_TOO_LONG',
            '415' => 'UNSUPPORTED_MEDIA_TYPE',
            '418' => 'TEAPOT',
            '500' => 'INTERNAL_SERVER_ERROR',
            '501' => 'NOT_IMPLEMENTED',
            '502' => 'BAD_GATEWAY',
            '503' => 'SERVICE_UNAVAILABLE',
            '504' => 'GATEWAY_TIMOUT',
            '505' => 'HTTP_VERSION_NOT_SUPPORTED',
        ];
        $error_message_tag = (isset($error_messages_list[$status_code]) ? $error_messages_list[$status_code] : 'ERROR_UNKNOWN');

        // log
        if ($this->config['error_pages_log']) {
            $this->log->add(
                'critical',
                $this->user->data['user_id'],
                $this->user->data['session_ip'],
                'LOG_GENERAL_ERROR',
                false,
                array(
                    $status_code . ': ' . $this->lang->lang($error_message_tag),
                    $request->getPathInfo()
                )
            );
        }

        // print
        page_header($this->lang->lang('INFORMATION'));
        $this->template->assign_vars(array(
            'MESSAGE_TITLE' => $this->lang->lang('INFORMATION'),
            'MESSAGE_TEXT' => $this->lang->lang($error_message_tag) . '<br />' . (($this->config['error_pages_explain']) ? $this->lang->lang("{$error_message_tag}EXPA") : '') . '<br /><br />' . sprintf($this->lang->lang('RETURN_INDEX'), '<a href="/">', '</a>'),
        ));

        $this->template->set_filenames(array(
            'body' => 'message_body.html',
        ));
        page_footer(true, false, false);

        $response = new Response($this->template->assign_display('body'), $status_code);
        $event->setResponse($response);
    }
}
