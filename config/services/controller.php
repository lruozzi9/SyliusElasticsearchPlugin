<?php

declare(strict_types=1);

namespace Symfony\Component\DependencyInjection\Loader\Configurator;

use Webgriffe\SyliusElasticsearchPlugin\Controller\ElasticsearchController;

return static function (ContainerConfigurator $containerConfigurator) {
    $services = $containerConfigurator->services();

    $services->set('webgriffe.sylius_elasticsearch_plugin.controller.elasticsearch', ElasticsearchController::class)
        ->args([
            service('sylius.repository.taxon'),
            service('sylius.context.locale'),
            service('webgriffe.sylius_elasticsearch_plugin.client'),
            service('sylius.context.channel'),
            service('webgriffe.sylius_elasticsearch_plugin.generator.index_name'),
            service('webgriffe.sylius_elasticsearch_plugin.provider.document_type'),
            service('webgriffe.sylius_elasticsearch_plugin.parser.elasticsearch_document'),
            service('form.factory'),
            service('webgriffe.sylius_elasticsearch_plugin.builder.query'),
        ])
        ->call('setContainer', [service('service_container')])
        ->tag('controller.service_arguments')
        ->tag('container.service_subscriber')
    ;
};
