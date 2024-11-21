<?php
/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

return [
    'routes' => [
        ['name' => 'config#oauthRedirect', 'url' => '/oauth-redirect', 'verb' => 'GET'],
        ['name' => 'config#oauthProtocolRedirect', 'url' => '/oauth-protocol-redirect', 'verb' => 'GET'],
        ['name' => 'config#setConfig', 'url' => '/config', 'verb' => 'PUT'],
        ['name' => 'config#setSensitiveConfig', 'url' => '/sensitive-config', 'verb' => 'PUT'],
        ['name' => 'discourseAPI#getDiscourseUrl', 'url' => '/url', 'verb' => 'GET'],
        ['name' => 'discourseAPI#getDiscourseUsername', 'url' => '/username', 'verb' => 'GET'],
        ['name' => 'discourseAPI#getNotifications', 'url' => '/notifications', 'verb' => 'GET'],
        ['name' => 'discourseAPI#getDiscourseAvatar', 'url' => '/avatar', 'verb' => 'GET'],
    ]
];
