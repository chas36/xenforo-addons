<?php

namespace Alebarda\UserPosts;

use XF\AddOn\AbstractSetup;
use XF\Db\Schema\Create;

class Setup extends AbstractSetup
{
    public function install(array $stepParams = [])
    {
        // Register API route
        $this->createRoute();
    }

    public function upgrade(array $stepParams = [])
    {
        // Ensure route exists on upgrade
        $this->createRoute();
    }

    public function uninstall(array $stepParams = [])
    {
        // Remove route on uninstall
        $this->deleteRoute();
    }

    protected function createRoute()
    {
        $db = $this->db();

        // Check if route already exists
        $exists = $db->fetchOne("
            SELECT route_id FROM xf_route
            WHERE route_type = 'api' AND route_prefix = 'user-posts'
        ");

        if (!$exists) {
            $db->insert('xf_route', [
                'route_type' => 'api',
                'route_prefix' => 'user-posts',
                'sub_name' => '',
                'format' => '',
                'build_class' => '',
                'build_method' => '',
                'controller' => 'Alebarda\\UserPosts\\Api\\Controller\\UserPosts',
                'context' => '',
                'action_prefix' => '',
                'addon_id' => 'Alebarda/UserPosts'
            ]);

            // Rebuild route cache
            \XF::app()->registry()->delete('routesApi');
        }
    }

    protected function deleteRoute()
    {
        $this->db()->delete('xf_route', "route_type = 'api' AND route_prefix = 'user-posts'");
        \XF::app()->registry()->delete('routesApi');
    }
}
