<?php
/**
 * Created by PhpStorm.
 * User: krona
 * Date: 4/22/14
 * Time: 2:42 PM
 */

namespace Arilas\Whoops;

use Arilas\Whoops\Handler\CallbackHandler as ArilasCallbackHandler;
use Whoops\Handler\CallbackHandler;
use Whoops\Handler\JsonResponseHandler;
use Whoops\Handler\PrettyPageHandler;
use Whoops\Run;
use Zend\EventManager\EventInterface;
use Zend\Loader\AutoloaderFactory;
use Zend\Loader\StandardAutoloader;
use Zend\ModuleManager\Feature\BootstrapListenerInterface;
use Zend\Mvc\Application;
use Zend\Mvc\MvcEvent;
use Zend\ServiceManager\ServiceLocatorInterface;
use Zend\Stdlib\Response;

class Module implements BootstrapListenerInterface
{
    /** @var Run */
    protected $run = null;
    /** @var array */
    protected $whoopsConfig = array();
    /** @var  Zend\ServiceManager\ServiceLocatorInterface */
    protected $serviceLocator;

    public function getAutoloaderConfig()
    {
        return [
            AutoloaderFactory::STANDARD_AUTOLOADER => [
                StandardAutoloader::LOAD_NS => [
                    __NAMESPACE__ => __DIR__,
                ],
            ],
        ];
    }

    public function getConfig()
    {
        return include __DIR__ . '/Resources/config/module.config.php';
    }

    public function onBootstrap(EventInterface $e)
    {
        $this->serviceLocator = $e->getTarget()->getServiceManager();
        $config = $e->getTarget()->getServiceManager()->get('Config');
        $this->whoopsConfig = $config['arilas']['whoops'];

        if ($this->whoopsConfig['disabled']) {
            return;
        }

        $this->run = new Run();
        $this->run->register();

        $this->run->pushHandler($this->getHandler());

        $eventManager = $e->getTarget()->getEventManager();
        $eventManager->attach(MvcEvent::EVENT_RENDER_ERROR, array($this, 'prepareException'));
        $eventManager->attach(MvcEvent::EVENT_DISPATCH_ERROR, array($this, 'prepareException'));
    }

    protected function getHandler()
    {
        $options = $this->whoopsConfig['handler'];
        $handler = new $options['type']($options['options']);
        $initiator = $options['options_type'] . 'Init';

        if (method_exists($this, $initiator)) {
            $this->$initiator($handler);
        }

        return $handler;
    }

    /**
     * Whoops handle exceptions
     * @param MvcEvent $e
     */
    public function prepareException(MvcEvent $e)
    {
        $error = $e->getError();
        if (!empty($error) && !$e->getResult() instanceof Response) {
            switch ($error) {
                case Application::ERROR_CONTROLLER_NOT_FOUND:
                case Application::ERROR_CONTROLLER_INVALID:
                case Application::ERROR_ROUTER_NO_MATCH:
                    // Specifically not handling these
                    return;

                case Application::ERROR_EXCEPTION:
                default:
                    if (in_array(get_class($e->getParam('exception')), $this->whoopsConfig['blacklist'])) {
                        // No catch this exception
                        return;
                    }

                    if ($this->whoopsConfig['handler']['options_type'] === 'prettyPage') {
                        $response = $e->getResponse();
                        if (!$response || $response->getStatusCode() === 200) {
                            header('HTTP/1.0 500 Internal Server Error', true, 500);
                        }
                        ob_clean();
                    }

                    $this->run->handleException($e->getParam('exception'));
                    break;
            }
        }
    }

    protected function prettyPageInit(PrettyPageHandler $handler)
    {
        $options = $this->whoopsConfig['handler']['options'];
        $handler->setEditor($options['editor']);
    }

    protected function jsonResponseInit(JsonResponseHandler $handler)
    {
        $options = $this->whoopsConfig['handler']['options'];
        if (!empty($options['showTrace'])) {
            $handler->addTraceToOutput($options['showTrace']);
        }

        if (!empty($options['ajaxOnly'])) {
            $handler->onlyForAjaxRequests($options['ajaxOnly']);
        }
    }

    protected function callbackInit(CallbackHandler $handler)
    {

    }

    protected function locatorInit(ArilasCallbackHandler $handler)
    {
        $handler->setServiceLocator($this->serviceLocator);
    }
}