/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

import { getCSPNonce } from '@nextcloud/auth'
import Vue from 'vue'
import './bootstrap.js'
import Dashboard from './views/Dashboard.vue'

__webpack_nonce__ = getCSPNonce() // eslint-disable-line

document.addEventListener('DOMContentLoaded', function() {
	if (!OCA.Dashboard) {
		return
	}

	OCA.Dashboard.register('discourse_notifications', (el, { widget }) => {
		const View = Vue.extend(Dashboard)
		return new View({
			propsData: {
				title: widget.title,
				widgetType: 'unread',
			},
		}).$mount(el)
	})

	OCA.Dashboard.register('discourse_notifications_read', (el, { widget }) => {
		const View = Vue.extend(Dashboard)
		return new View({
			propsData: {
				title: widget.title,
				widgetType: 'read',
			},
		}).$mount(el)
	})
})
