<?php

namespace RebelCode\EddBookings\Sessions\Module;

use AppendIterator;
use ArrayAccess;
use ArrayIterator;
use Dhii\Data\Container\ContainerGetCapableTrait;
use Dhii\Data\Container\ContainerGetPathCapableTrait;
use Dhii\Data\Container\CreateContainerExceptionCapableTrait;
use Dhii\Data\Container\CreateNotFoundExceptionCapableTrait;
use Dhii\Data\Container\NormalizeKeyCapableTrait;
use Dhii\Exception\CreateInvalidArgumentExceptionCapableTrait;
use Dhii\Exception\CreateOutOfRangeExceptionCapableTrait;
use Dhii\Factory\FactoryInterface;
use Dhii\I18n\StringTranslatingTrait;
use Dhii\Invocation\InvocableInterface;
use Dhii\Iterator\NormalizeIteratorCapableTrait;
use Dhii\Storage\Resource\DeleteCapableInterface;
use Dhii\Storage\Resource\InsertCapableInterface;
use Dhii\Util\Normalization\NormalizeArrayCapableTrait;
use Dhii\Util\Normalization\NormalizeIntCapableTrait;
use Dhii\Util\Normalization\NormalizeIterableCapableTrait;
use Dhii\Util\Normalization\NormalizeStringCapableTrait;
use Dhii\Util\String\StringableInterface as Stringable;
use IteratorIterator;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Psr\EventManager\EventInterface;
use RebelCode\Bookings\Availability\AvailabilityInterface;
use RebelCode\Bookings\Availability\AvailabilityPeriodInterface;
use RebelCode\Bookings\Availability\CompositeAvailability;
use RebelCode\Bookings\Availability\IntersectionAvailability;
use RebelCode\Bookings\Sessions\SessionGeneratorInterface;
use RebelCode\EddBookings\Sessions\Time\Period;
use RebelCode\EddBookings\Sessions\Util\ModifyCallbackIterator;
use RebelCode\Entity\GetCapableManagerInterface;
use RebelCode\Time\NormalizeTimestampCapableTrait;
use stdClass;
use Traversable;

/**
 * Handler class for the session generation event.
 *
 * @since [*next-version*]
 */
class GenerateSessionsHandler implements InvocableInterface
{
    /* @since [*next-version*] */
    use ContainerGetPathCapableTrait;

    /* @since [*next-version*] */
    use ContainerGetCapableTrait;

    /* @since [*next-version*] */
    use NormalizeTimestampCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIterableCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIteratorCapableTrait;

    /* @since [*next-version*] */
    use NormalizeIntCapableTrait;

    /* @since [*next-version*] */
    use NormalizeKeyCapableTrait;

    /* @since [*next-version*] */
    use NormalizeStringCapableTrait;

    /* @since [*next-version*] */
    use NormalizeArrayCapableTrait;

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
     * The session generator.
     *
     * @since [*next-version*]
     *
     * @var SessionGeneratorInterface
     */
    protected $generator;

    /**
     * The services entity manager.
     *
     * @since [*next-version*]
     *
     * @var GetCapableManagerInterface
     */
    protected $servicesManager;

    /**
     * The resources entity manager.
     *
     * @since [*next-version*]
     *
     * @var GetCapableManagerInterface
     */
    protected $resourcesManager;

    /**
     * The factory for creating session types.
     *
     * @since [*next-version*]
     *
     * @var FactoryInterface
     */
    protected $sessionTypeFactory;

    /**
     * The factory for creating availabilities.
     *
     * @since [*next-version*]
     *
     * @var FactoryInterface
     */
    protected $availabilityFactory;

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
     * Constructor.
     *
     * @since [*next-version*]
     *
     * @param SessionGeneratorInterface  $generator           The session generator.
     * @param GetCapableManagerInterface $servicesManager     The services entity manager.
     * @param GetCapableManagerInterface $resourcesManager    The resources entity manager.
     * @param FactoryInterface           $sessionTypeFactory  The factory for creating session types.
     * @param FactoryInterface           $availabilityFactory The factory for creating availabilities.
     * @param InsertCapableInterface     $sessionsInsertRm    The INSERT RM for sessions.
     * @param DeleteCapableInterface     $sessionsDeleteRm    The DELETE RM for sessions.
     * @param object                     $exprBuilder         The expression builder.
     */
    public function __construct(
        SessionGeneratorInterface $generator,
        GetCapableManagerInterface $servicesManager,
        GetCapableManagerInterface $resourcesManager,
        FactoryInterface $sessionTypeFactory,
        FactoryInterface $availabilityFactory,
        InsertCapableInterface $sessionsInsertRm,
        DeleteCapableInterface $sessionsDeleteRm,
        $exprBuilder
    ) {
        $this->generator           = $generator;
        $this->servicesManager     = $servicesManager;
        $this->resourcesManager    = $resourcesManager;
        $this->sessionTypeFactory  = $sessionTypeFactory;
        $this->availabilityFactory = $availabilityFactory;
        $this->sessionsInsertRm    = $sessionsInsertRm;
        $this->sessionsDeleteRm    = $sessionsDeleteRm;
        $this->exprBuilder         = $exprBuilder;
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
        // Get the service's schedule availability, since service availability it saved in its schedule
        $service    = $this->servicesManager->get($serviceId);
        $scheduleId = $this->_containerGet($service, 'schedule_id');
        $schedule   = $this->resourcesManager->get($scheduleId);
        $scheduleAv = $this->_getResourceAvailability($schedule);

        // Create the resource availabilities
        // The resources are retrieved from the service's session types
        // Each session type has a list of resources associated with it
        // Therefore, we need to iterate the session types, obtain their resources and get their availabilities
        // Session type instances are also prepared during this iteration since they are required by the generator
        // A cache of resources, keyed by their IDs, is also kept to minimize calls to the entity manager.
        $resources        = [];
        $resourceAvs      = [];
        $sessionTypes     = [];
        $sessionTypesData = $this->_containerGet($service, 'session_types');

        foreach ($sessionTypesData as $_data) {
            // Create and store session type instance
            $_sessionType = $this->sessionTypeFactory->make($_data);

            try {
                $_stResourceIds = $this->_containerGetPath($_data, ['data', 'resources']);
            } catch (NotFoundExceptionInterface $exception) {
                continue;
            }

            $sessionTypes[] = [
                'object'    => $_sessionType,
                'resources' => $_stResourceIds,
            ];

            foreach ($_stResourceIds as $_resourceId) {
                try {
                    // Get from cache first if available, otherwise get using the entity manager
                    $_resource = !isset($resources)
                        ? $this->resourcesManager->get($_resourceId)
                        : $resources[$_resourceId];
                    // Get and store the resource availability
                    $resourceAvs[] = $this->_getResourceAvailability($_resource);
                } catch (NotFoundExceptionInterface $exception) {
                    continue;
                }
            }
        }

        // The final service availability is the intersection of the schedule availability with the composite
        // availability of all the resources retrieved from the session types.
        // The composition of resources yields all the available periods for all the resources.
        // The intersection restricts those periods to those that are also present in the schedule
        $availability = (count($resourceAvs) > 0)
            ? new IntersectionAvailability([$scheduleAv, new CompositeAvailability($resourceAvs)])
            : $scheduleAv;

        // Use an append iterator to incrementally add more iterators, as retrieved from each generation pass
        $sessions = new AppendIterator();
        // Generate for 5 years by default
        $range = new Period(time(), strtotime('+5 years'));

        // Iterate all available periods of time
        // For each period, check which session types need to be generated for it. This is determined by resource ID
        // equivalence. If the availability period and the session types have the same resources, then the session
        // type may be used to generate sessions for that period.
        /* @var $_period AvailabilityPeriodInterface */
        foreach ($availability->getAvailablePeriods($range) as $_period) {
            $_sessionTypes = [];

            foreach ($sessionTypes as $_sessionType) {
                $diff = array_diff($_sessionType['resources'], $_period->getResourceIds());

                if (count($diff) === 0) {
                    $_sessionTypes[] = $_sessionType['object'];
                }
            }

            $sessions->append(
                $this->_normalizeIterator(
                    $this->generator->generate($_period, $_sessionTypes)
                )
            );
        }

        // Use a callback iterator to modify each session at the last minute to add the service ID
        $finalSessions = new ModifyCallbackIterator($sessions, function ($session) use ($serviceId) {
            $session['service_id'] = $serviceId;

            return $session;
        });

        // Delete all existing sessions for this service
        $b = $this->exprBuilder;
        $this->sessionsDeleteRm->delete($b->eq(
            $b->var('service_id'),
            $b->lit($serviceId)
        ));

        // Save the newly generated session
        $this->sessionsInsertRm->insert($finalSessions);
    }

    /**
     * Retrieve a resource's availability.
     *
     * @since [*next-version*]
     *
     * @param array|stdClass|ArrayAccess|ContainerInterface $resource The resource data.
     *
     * @return AvailabilityInterface The resource's full availability.
     */
    protected function _getResourceAvailability($resource)
    {
        $availRules     = $this->_containerGetPath($resource, ['availability', 'rules']);
        $timezone       = $this->_containerGetPath($resource, ['availability', 'timezone']);
        $availabilities = [];

        foreach ($availRules as $_ruleData) {
            $_config             = $this->_normalizeArray($_ruleData);
            $_config['timezone'] = $timezone;
            $availabilities[]    = $this->availabilityFactory->make($_config);
        }

        return new CompositeAvailability($availabilities);
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

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _createArrayIterator(array $array)
    {
        return new ArrayIterator($array);
    }

    /**
     * {@inheritdoc}
     *
     * @since [*next-version*]
     */
    protected function _createTraversableIterator(Traversable $traversable)
    {
        return new IteratorIterator($traversable);
    }
}
