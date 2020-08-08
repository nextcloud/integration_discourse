<template>
	<DashboardWidget :items="items"
		:showMoreUrl="showMoreUrl"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<div v-if="state === 'no-token'">
				<a :href="settingsUrl">
					{{ t('discourse', 'Click here to configure the access to your Discourse account.') }}
				</a>
			</div>
			<div v-else-if="state === 'error'">
				<a :href="settingsUrl">
					{{ t('discourse', 'Incorrect API key.') }}
					{{ t('discourse', 'Click here to configure the access to your Discourse account.') }}
				</a>
			</div>
			<div v-else-if="state === 'ok'">
				{{ t('discourse', 'Nothing to show') }}
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
import { getLocale } from '@nextcloud/l10n'

const TYPE_MENTION = 1
const TYPE_REPLY = 2
const TYPE_QUOTED = 3
const TYPE_EDIT = 4
const TYPE_LIKE = 5
const TYPE_PRIVATE_MESSAGE = 6
const TYPE_REPLY_2 = 9
const TYPE_LINKED = 11
const TYPE_BADGE_EARNED = 12
const TYPE_SOLVED = 14
const TYPE_GROUP_MENTION = 15

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget,
	},

	props: [],

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
				const response = await axios.get(generateUrl('/apps/discourse/url'))
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
			axios.get(generateUrl('/apps/discourse/notifications'), req).then((response) => {
				this.processNotifications(response.data)
				this.state = 'ok'
			}).catch((error) => {
				clearInterval(this.loop)
				if (error.response && error.response.status === 400) {
					this.state = 'no-token'
				} else if (error.response && error.response.status === 401) {
					showError(t('discourse', 'Failed to get Discourse notifications.'))
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
				return (!n.read && ![TYPE_BADGE_EARNED].includes(n.notification_type))
			})
		},
		getNotificationTarget(n) {
			if ([TYPE_MENTION, TYPE_PRIVATE_MESSAGE].includes(n.notification_type)) {
				return this.discourseUrl + '/t/' + n.slug + '/' + n.topic_id
			} else if ([TYPE_REPLY, TYPE_REPLY_2, TYPE_LIKE, TYPE_SOLVED].includes(n.notification_type)) {
				return this.discourseUrl + '/t/' + n.slug + '/' + n.topic_id + '/' + n.post_number
			} else if ([TYPE_BADGE_EARNED].includes(n.notification_type)) {
				return this.discourseUrl + '/badges/' + n.data.badge_id + '/' + n.data.badge_slug + '?username=' + n.data.username
			}
			return ''
		},
		getUniqueKey(n) {
			return n.id
		},
		getNotificationImage(n) {
			if ([TYPE_PRIVATE_MESSAGE, TYPE_MENTION, TYPE_REPLY, TYPE_REPLY_2, TYPE_LIKE].includes(n.notification_type)) {
				return (n.data.original_username)
					? generateUrl('/apps/discourse/avatar?') + encodeURIComponent('username') + '=' + encodeURIComponent(n.data.original_username)
					: ''
			} else if ([TYPE_SOLVED].includes(n.notification_type)) {
				return (n.data.display_username)
					? generateUrl('/apps/discourse/avatar?') + encodeURIComponent('username') + '=' + encodeURIComponent(n.data.display_username)
					: ''
			}
			// nothing for badges
			return ''
		},
		getNotificationTypeImage(n) {
			if (n.notification_type === TYPE_PRIVATE_MESSAGE) {
				return generateUrl('/svg/discourse/message?color=ffffff')
			} else if (n.notification_type === TYPE_MENTION) {
				return generateUrl('/svg/discourse/arobase?color=ffffff')
			} else if (n.notification_type === TYPE_LIKE) {
				return generateUrl('/svg/discourse/heart?color=ffffff')
			} else if ([TYPE_REPLY, TYPE_REPLY_2].includes(n.notification_type)) {
				return generateUrl('/svg/discourse/reply?color=ffffff')
			} else if (n.notification_type === TYPE_BADGE_EARNED) {
				return generateUrl('/svg/discourse/badge?color=ffffff')
			} else if (n.notification_type === TYPE_SOLVED) {
				return generateUrl('/svg/discourse/solved?color=ffffff')
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
			if ([TYPE_PRIVATE_MESSAGE, TYPE_MENTION, TYPE_LIKE, TYPE_REPLY, TYPE_REPLY_2].includes(n.notification_type)) {
				return this.getDisplayAndOriginalUsername(n)
			} else if (n.notification_type === TYPE_SOLVED) {
				return '@' + n.display_username
			} else if (n.notification_type === TYPE_BADGE_EARNED) {
				return n.data.badge_name
			}
			return ''
		},
		getAuthorFullName(n) {
			if ([TYPE_PRIVATE_MESSAGE, TYPE_MENTION, TYPE_LIKE, TYPE_REPLY, TYPE_REPLY_2].includes(n.notification_type)) {
				return n.data.original_username
			} else if (n.notification_type === TYPE_SOLVED) {
				return n.display_username
			} else if (n.notification_type === TYPE_BADGE_EARNED) {
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
