<template>
	<DashboardWidget :items="items"
		:showMoreUrl="showMoreUrl"
		:showMoreText="title"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<div v-if="state === 'no-token'">
				<a :href="settingsUrl">
					{{ t('integration_discourse', 'Click here to configure the access to your Discourse account.') }}
				</a>
			</div>
			<div v-else-if="state === 'error'">
				<a :href="settingsUrl">
					{{ t('integration_discourse', 'Incorrect API key.') }}
					{{ t('integration_discourse', 'Click here to configure the access to your Discourse account.') }}
				</a>
			</div>
			<div v-else-if="state === 'ok'">
				{{ t('integration_discourse', 'Nothing to show') }}
			</div>
		</template>
	</DashboardWidget>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl } from '@nextcloud/router'
import { DashboardWidget } from '@nextcloud/vue-dashboard'
import { showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
// eslint-disable-next-line
import { getLocale } from '@nextcloud/l10n'

const TYPES = {
	MENTION: 1,
	REPLY: 2,
	QUOTED: 3,
	EDIT: 4,
	LIKE: 5,
	PRIVATE_MESSAGE: 6,
	REPLY_2: 9,
	LINKED: 11,
	BADGE_EARNED: 12,
	SOLVED: 14,
	GROUP_MENTION: 15,
}

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
	},

	props: {
		title: {
			type: String,
			required: true,
		},
	},

	data() {
		return {
			discourseUrl: null,
			notifications: [],
			locale: getLocale(),
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/linked-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			hovered: {},
		}
	},

	computed: {
		showMoreUrl() {
			return this.discourseUrl
		},
		items() {
			return this.notifications.map((n) => {
				return {
					id: this.getUniqueKey(n),
					targetUrl: this.getNotificationTarget(n),
					avatarUrl: this.getNotificationImage(n),
					avatarUsername: this.getAuthorFullName(n),
					overlayIconUrl: this.getNotificationTypeImage(n),
					mainText: this.getTargetTitle(n),
					subText: this.getSubline(n),
				}
			})
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].created_at : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get discourse URL first
			try {
				const response = await axios.get(generateUrl('/apps/integration_discourse/url'))
				this.discourseUrl = response.data.replace(/\/+$/, '')
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 15000)
		},
		fetchNotifications() {
			const req = {}
			if (this.lastDate) {
				req.params = {
					since: this.lastDate,
				}
			}
			axios.get(generateUrl('/apps/integration_discourse/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('integration_discourse', 'Failed to get Discourse notifications.'))
					this.state = 'error'
				} else {
					// there was an error in notif processing
					console.debug(error)
				}
			})
		},
		processNotifications(newNotifications) {
			if (this.lastDate) {
				// just add those which are more recent than our most recent one
				let i = 0
				while (i < newNotifications.length && this.lastMoment.isBefore(newNotifications[i].created_at)) {
					i++
				}
				if (i > 0) {
					const toAdd = this.filter(newNotifications.slice(0, i))
					this.notifications = toAdd.concat(this.notifications)
				}
			} else {
				// first time we don't check the date
				this.notifications = this.filter(newNotifications)
			}
		},
		filter(notifications) {
			return notifications.filter((n) => {
				return (!n.read && ![TYPES.BADGE_EARNED].includes(n.notification_type))
			})
		},
		getNotificationTarget(n) {
			if ([TYPES.MENTION, TYPES.PRIVATE_MESSAGE].includes(n.notification_type)) {
				return this.discourseUrl + '/t/' + n.slug + '/' + n.topic_id
			} else if ([TYPES.REPLY, TYPES.REPLY_2, TYPES.LIKE, TYPES.SOLVED].includes(n.notification_type)) {
				return this.discourseUrl + '/t/' + n.slug + '/' + n.topic_id + '/' + n.post_number
			} else if ([TYPES.BADGE_EARNED].includes(n.notification_type)) {
				return this.discourseUrl + '/badges/' + n.data.badge_id + '/' + n.data.badge_slug + '?username=' + n.data.username
			}
			return ''
		},
		getUniqueKey(n) {
			return n.id
		},
		getNotificationImage(n) {
			if ([TYPES.PRIVATE_MESSAGE, TYPES.MENTION, TYPES.REPLY, TYPES.REPLY_2, TYPES.LIKE].includes(n.notification_type)) {
				return (n.data.original_username)
					? generateUrl('/apps/integration_discourse/avatar?') + encodeURIComponent('username') + '=' + encodeURIComponent(n.data.original_username)
					: ''
			} else if ([TYPES.SOLVED].includes(n.notification_type)) {
				return (n.data.display_username)
					? generateUrl('/apps/integration_discourse/avatar?') + encodeURIComponent('username') + '=' + encodeURIComponent(n.data.display_username)
					: ''
			}
			// nothing for badges
			return ''
		},
		getNotificationTypeImage(n) {
			if (n.notification_type === TYPES.PRIVATE_MESSAGE) {
				return generateUrl('/svg/integration_discourse/message?color=ffffff')
			} else if (n.notification_type === TYPES.MENTION) {
				return generateUrl('/svg/integration_discourse/arobase?color=ffffff')
			} else if (n.notification_type === TYPES.LIKE) {
				return generateUrl('/svg/integration_discourse/heart?color=ffffff')
			} else if ([TYPES.REPLY, TYPES.REPLY_2].includes(n.notification_type)) {
				return generateUrl('/svg/integration_discourse/reply?color=ffffff')
			} else if (n.notification_type === TYPES.BADGE_EARNED) {
				return generateUrl('/svg/integration_discourse/badge?color=ffffff')
			} else if (n.notification_type === TYPES.SOLVED) {
				return generateUrl('/svg/integration_discourse/solved?color=ffffff')
			}
			return generateUrl('/svg/core/actions/sound?color=' + this.themingColor)
		},
		getDisplayAndOriginalUsername(n) {
			if (n.data.display_username && n.data.display_username !== n.data.original_username) {
				return n.data.display_username + '(@' + n.data.original_username + ')'
			} else {
				return n.data.display_username
			}
		},
		getSubline(n) {
			if ([TYPES.PRIVATE_MESSAGE, TYPES.MENTION, TYPES.LIKE, TYPES.REPLY, TYPES.REPLY_2].includes(n.notification_type)) {
				return this.getDisplayAndOriginalUsername(n)
			} else if (n.notification_type === TYPES.SOLVED) {
				return '@' + n.display_username
			} else if (n.notification_type === TYPES.BADGE_EARNED) {
				return n.data.badge_name
			}
			return ''
		},
		getAuthorFullName(n) {
			if ([TYPES.PRIVATE_MESSAGE, TYPES.MENTION, TYPES.LIKE, TYPES.REPLY, TYPES.REPLY_2].includes(n.notification_type)) {
				return n.data.original_username
			} else if (n.notification_type === TYPES.SOLVED) {
				return n.display_username
			} else if (n.notification_type === TYPES.BADGE_EARNED) {
				return '*'
			}
			return ''
		},
		getTargetTitle(n) {
			return n.fancy_title
		},
		getFormattedDate(n) {
			return moment(n.created_at).locale(this.locale).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
</style>
