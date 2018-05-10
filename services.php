<?php

use Dhii\Factory\GenericCallbackFactory;
use Psr\Container\ContainerInterface;
use RebelCode\EddBookings\Sessions\Module\GenerateSessionsHandler;
use RebelCode\EddBookings\Sessions\Module\SessionRuleFactory;
use RebelCode\EddBookings\Sessions\Time\PeriodFactory;
use RebelCode\Sessions\SessionGenerator;

return [
    'eddbk_session_generator_factory' => function (ContainerInterface $c) {
        return new GenericCallbackFactory(function ($config) {
            $sessionFactory = $this->_containerGet($config, 'session_factory');
            $sessionLengths = $this->_containerGet($config, 'session_lengths');

            return new SessionGenerator($sessionFactory, $sessionLengths);
        });
    },
    'eddbk_generate_sessions_handler' => function (ContainerInterface $c) {
        return new GenerateSessionsHandler(
            $c->get('eddbk_session_generator_factory'),
            $c->get('eddbk_session_rule_factory'),
            $c->get('session_rules_select_rm'),
            $c->get('sessions_insert_rm'),
            $c->get('sessions_delete_rm'),
            $c->get('sql_expression_builder')
        );
    },
    'eddbk_session_rule_factory'      => function (ContainerInterface $c) {
        return new SessionRuleFactory($c->get('eddbk_period_factory'));
    },
    'eddbk_period_factory'            => function (ContainerInterface $c) {
        return new PeriodFactory();
    },
];
