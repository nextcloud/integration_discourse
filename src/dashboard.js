/**
 * SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

document.addEventListener('DOMContentLoaded', async () => {
	if (!OCA.Dashboard) {
		return
	}
	const { createApp } = await import('vue')
	const { default: Dashboard } = await import('./views/Dashboard.vue')

	OCA.Dashboard.register('discourse_notifications', async (el, { widget }) => {
		const app = createApp(
			Dashboard,
			{
				title: widget.title,
				widgetType: 'unread',
			},
		)
		app.mixin({ methods: { t, n } })
		app.mount(el)
	})

	OCA.Dashboard.register('discourse_notifications_read', async (el, { widget }) => {
		const app = createApp(
			Dashboard,
			{
				title: widget.title,
				widgetType: 'read',
			},
		)
		app.mixin({ methods: { t, n } })
		app.mount(el)
	})
})
