<?php
namespace Grav\Plugin;

use Grav\Common\Plugin;
use Grav\Common\Twig;
use RocketTheme\Toolbox\Event\Event;

class EmailPlugin extends Plugin
{
    /**
     * @var Email
     */
    protected $email;

    /**
     * @return array
     */
    public static function getSubscribedEvents()
    {
        return [
            'onPluginsInitialized' => ['onPluginsInitialized', 0],
            'onFormProcessed' => ['onFormProcessed', 0]
        ];
    }

    /**
     * Initialize emailing.
     */
    public function onPluginsInitialized()
    {
        require_once __DIR__ . '/classes/email.php';
        require_once __DIR__ . '/vendor/autoload.php';

        $this->email = new Email();

        if ($this->email->enabled()) {
            $this->grav['Email'] = $this->email;
        }
    }

    /**
     * Send email when processing the form data.
     *
     * @param Event $event
     */
    public function onFormProcessed(Event $event)
    {
        $form = $event['form'];
        $action = $event['action'];
        $params = $event['params'];

        if (!$this->email->enabled()) {
            return;
        }

        switch ($action) {
            case 'email':
                // Prepare Twig variables
                $vars = array(
                    'form' => $form
                );

                // Build message
                $message = $this->buildMessage($params, $vars);

                // Send e-mail
                $this->email->send($message);
                break;
        }
    }

    /**
     * Build e-mail message.
     *
     * @param array $params
     * @param array $vars
     * @return \Swift_Message
     */
    protected function buildMessage(array $params, array $vars = array())
    {
        /** @var Twig $twig */
        $twig = $this->grav['twig'];

        // Extend parameters with defaults.
        $params += array(
            'body' => '{% include "forms/data.html.twig" %}',
            'from' => $this->config->get('plugins.email.from'),
            'subject' => !empty($vars['form']) && $vars['form'] instanceof Form ? $vars['form']->page()->title() : null,
            'to' => (array) $this->config->get('plugins.email.to'),
        );

        // Create message object.
        $message = $this->email->message();

        // Process parameters.
        foreach ($params as $key => $value) {
            switch ($key) {
                case 'body':
                    $message->setBody($twig->processString($value, $vars));
                    break;

                case 'from':
                    $message->setFrom($twig->processString($value, $vars));
                    break;

                case 'subject':
                    $message->setSubject($twig->processString($value, $vars));
                    break;

                case 'to':
                    $value = (array) $value;
                    foreach ($value as &$address) {
                        $message->addTo($twig->processString($address, $vars));
                    }
                    break;
            }
        }

        return $message;
    }
}
