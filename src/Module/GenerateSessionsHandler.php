<?php

namespace RebelCode\EddBookings\Sessions\Module;

use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\DeleteCapableInterface;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\Factory\FactoryInterface;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Dhii\Storage\Resource\InsertCapableInterface;
use Dhii\Storage\Resource\SelectCapableInterface;
use Dhii\Time\PeriodInterface;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
use Psr\EventManager\EventInterface;
use RebelCode\Sessions\SessionGenerator;
use RebelCode\Sessions\SessionGeneratorInterface;
use RebelCode\Time\NormalizeTimestampCapableTrait;
use Traversable;

/**
 * Handler class for the session generation event.
 *
 * @since [*next-version*]
 */
class GenerateSessionsHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use NormalizeTimestampCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIntCapableTrait;

    /* @since [*next-version*] */
    use NormalizeKeyCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use CreateContainerExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateNotFoundExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateInvalidArgumentExceptionCapableTrait;

    /* @since [*next-version*] */
    use CreateOutOfRangeExceptionCapableTrait;

    /* @since [*next-version*] */
    use StringTranslatingTrait;

    /**
     * The SELECT RM for session generator rule.
     *
     * @since [*next-version*]
     *
     * @var SelectCapableInterface
     */
    protected $rulesSelectRm;

    /**
     * The INSERT RM for sessions.
     *
     * @since [*next-version*]
     *
     * @var InsertCapableInterface
     */
    protected $sessionsInsertRm;

    /**
     * The DELETE RM for sessions.
     *
     * @since [*next-version*]
     *
     * @var DeleteCapableInterface
     */
    protected $sessionsDeleteRm;

    /**
     * The expression builder.
     *
     * @since [*next-version*]
     *
     * @var object
     */
    protected $exprBuilder;

    /**
     * The session generator rule factory.
     *
     * @since [*next-version*]
     *
     * @var FactoryInterface
     */
    protected $ruleFactory;

    /**
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param SelectCapableInterface $rulesSelectRm    The SELECT RM for session generator rules.
     * @param InsertCapableInterface $sessionsInsertRm The INSERT RM for sessions.
     * @param DeleteCapableInterface $sessionsDeleteRm The DELETE RM for sessions.
     * @param object                 $exprBuilder      The expression builder.
     * @param FactoryInterface       $ruleFactory      The session generator rule factory.
     */
    public function __construct(
        $rulesSelectRm,
        $sessionsInsertRm,
        $sessionsDeleteRm,
        $exprBuilder,
        $ruleFactory
    ) {
        $this->rulesSelectRm    = $rulesSelectRm;
        $this->sessionsInsertRm = $sessionsInsertRm;
        $this->sessionsDeleteRm = $sessionsDeleteRm;
        $this->exprBuilder      = $exprBuilder;
        $this->ruleFactory      = $ruleFactory;
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    public function __invoke()
    {
        $event = func_get_arg(0);

        if (!($event instanceof EventInterface)) {
            throw $this->_createInvalidArgumentException(
                $this->__('Argument is not an event instance'), null, null, $event
            );
        }

        $serviceId = $event->getParam('service_id');

        if ($serviceId === null) {
            return;
        }

        $this->_generateForService($serviceId);
    }

    /**
     * Generates sessions for a particular service, by ID.
     *
     * @since [*next-version*]
     *
     * @param int|string|Stringable $serviceId The ID of the service for which to generate sessions.
     */
    protected function _generateForService($serviceId)
    {
        $postId = func_get_arg(0);

        // Get the session lengths for the service
        $sessionLengths = $this->_getPostMeta($postId, 'eddbk_session_lengths', true);
        // Initialize a generator with the lengths
        $generator = new SessionGenerator($this->_getSessionFactory($postId, $postId), $sessionLengths);

        $b = $this->exprBuilder;
        // Get the session generator rules for the service
        $rules = $this->rulesSelectRm->select(
            $b->eq(
                $b->ef('session_rule', 'service_id'),
                $b->lit($postId)
            )
        );

        foreach ($rules as $_ruleCfg) {
            $_rule   = $this->ruleFactory->make($_ruleCfg);
            $_ruleId = $this->_containerGet($_ruleCfg, 'id');

            $this->sessionsDeleteRm->delete($b->eq(
                $b->var('rule_id'),
                $b->lit($_ruleId)
            ));

            $this->_generateForRule($_rule, $generator);
        }
    }

    /**
     * Generates sessions for a single rule, using a specific generator.
     *
     * @since [*next-version*]
     *
     * @param Traversable               $rule      The rule as a traversable list of periods for each occurrence.
     * @param SessionGeneratorInterface $generator The session generator instance to use.
     */
    protected function _generateForRule($rule, $generator)
    {
        foreach ($rule as $occurrence) {
            /* @var $occurrence PeriodInterface */
            $sessions = $generator->generate(
                $this->_normalizeTimestamp($occurrence->getStart()),
                $this->_normalizeTimestamp($occurrence->getEnd())
            );

            $this->sessionsInsertRm->insert($sessions);
        }
    }

    /**
     * Retrieves the session factory to use when generating sessions for a given service and resource.
     *
     * @since [*next-version*]
     *
     * @param int|float|string|Stringable $serviceId  The service ID.
     * @param int|float|string|Stringable $resourceId The resource ID.
     *
     * @return callable The session factory, as a callable that receives the session start and end timestamps as
     *                  arguments and returns a session.
     */
    protected function _getSessionFactory($serviceId, $resourceId)
    {
        return function ($start, $end) use ($serviceId, $resourceId) {
            return [
                'start'       => $start,
                'end'         => $end,
                'service_id'  => $this->_normalizeInt($serviceId),
                'resource_id' => $this->_normalizeInt($resourceId),
            ];
        };
    }

    /**
     * Retrieves meta data for a WordPress post.
     *
     * @since [*next-version*]
     *
     * @param int|string $id      The ID of the service.
     * @param string     $metaKey The meta key.
     * @param mixed      $default The default value to return.
     *
     * @return mixed The meta value.
     */
    protected function _getPostMeta($id, $metaKey, $default = '')
    {
        $metaValue = get_post_meta($id, $metaKey, true);

        return ($metaValue === '')
            ? $default
            : $metaValue;
    }
}