<template>
	<DashboardWidget :items="items"
		:show-more-url="showMoreUrl"
		:show-more-text="title"
		:loading="state === 'loading'">
		<template v-slot:empty-content>
			<EmptyContent
				v-if="emptyContentMessage"
				:icon="emptyContentIcon">
				<template #desc>
					{{ emptyContentMessage }}
					<div v-if="state === 'no-token' || state === 'error'" class="connect-button">
						<a class="button" :href="settingsUrl">
							{{ t('integration_discourse', 'Connect to Discourse') }}
						</a>
					</div>
				</template>
			</EmptyContent>
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
import EmptyContent from '@nextcloud/vue/dist/Components/EmptyContent'

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
	MODERATOR_OR_ADMIN_INBOX: 16,
}

export default {
	name: 'Dashboard',

	components: {
		DashboardWidget, EmptyContent,
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
			username: '',
			notifications: [],
			locale: getLocale(),
			loop: null,
			state: 'loading',
			settingsUrl: generateUrl('/settings/user/connected-accounts'),
			themingColor: OCA.Theming ? OCA.Theming.color.replace('#', '') : '0082C9',
			hovered: {},
		}
	},

	computed: {
		showMoreUrl() {
			return this.discourseUrl + '/u/' + this.username + '/notifications'
		},
		items() {
			let notifications = this.notifications
			// if we have multiple admin inbox items, just show the last
			if (this.nbAdminInboxItem >= 2) {
				let found = false
				notifications = notifications.filter((n) => {
					if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX && n.data && n.data.group_name === 'admins') {
						if (found) {
							return false
						} else {
							found = true
							return true
						}
					}
					return true
				})
			}

			// if we have multiple moderator inbox items, just show the last
			if (this.nbModeratorInboxItem >= 2) {
				let found = false
				notifications = notifications.filter((n) => {
					if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX && n.data && n.data.group_name === 'moderators') {
						if (found) {
							return false
						} else {
							found = true
							return true
						}
					}
					return true
				})
			}

			return notifications.map((n) => {
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
		nbAdminInboxItem() {
			let nb = 0
			this.notifications.forEach((n) => {
				if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX && n.data && n.data.group_name === 'admins') {
					nb++
				}
			})
			return nb
		},
		nbModeratorInboxItem() {
			let nb = 0
			this.notifications.forEach((n) => {
				if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX && n.data && n.data.group_name === 'moderators') {
					nb++
				}
			})
			return nb
		},
		lastDate() {
			const nbNotif = this.notifications.length
			return (nbNotif > 0) ? this.notifications[0].created_at : null
		},
		lastMoment() {
			return moment(this.lastDate)
		},
		emptyContentMessage() {
			if (this.state === 'no-token') {
				return t('integration_discourse', 'No Discourse account connected')
			} else if (this.state === 'error') {
				return t('integration_discourse', 'Error connecting to Discourse')
			} else if (this.state === 'ok') {
				return t('integration_discourse', 'No Discourse notifications!')
			}
			return ''
		},
		emptyContentIcon() {
			if (this.state === 'no-token') {
				return 'icon-discourse'
			} else if (this.state === 'error') {
				return 'icon-close'
			} else if (this.state === 'ok') {
				return 'icon-checkmark'
			}
			return 'icon-checkmark'
		},
	},

	beforeMount() {
		this.launchLoop()
	},

	mounted() {
	},

	methods: {
		async launchLoop() {
			// get discourse URL and username first
			try {
				const response = await axios.get(generateUrl('/apps/integration_discourse/url'))
				this.discourseUrl = response.data.replace(/\/+$/, '')
				const responseU = await axios.get(generateUrl('/apps/integration_discourse/username'))
				this.username = responseU.data.replace(/\/+$/, '')
			} catch (error) {
				console.debug(error)
			}
			// then launch the loop
			this.fetchNotifications()
			this.loop = setInterval(() => this.fetchNotifications(), 60000)
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
				return (!n.read
					&& [
						TYPES.MENTION,
						TYPES.REPLY,
						TYPES.QUOTED,
						TYPES.EDIT,
						TYPES.LIKE,
						TYPES.PRIVATE_MESSAGE,
						TYPES.REPLY_2,
						TYPES.LINKED,
						TYPES.SOLVED,
						TYPES.GROUP_MENTION,
						TYPES.MODERATOR_OR_ADMIN_INBOX,
					].includes(n.notification_type))
			})
		},
		getNotificationTarget(n) {
			if ([TYPES.MENTION, TYPES.PRIVATE_MESSAGE].includes(n.notification_type)) {
				return this.discourseUrl + '/t/' + n.slug + '/' + n.topic_id
			} else if ([TYPES.REPLY, TYPES.REPLY_2, TYPES.LIKE, TYPES.SOLVED].includes(n.notification_type)) {
				return this.discourseUrl + '/t/' + n.slug + '/' + n.topic_id + '/' + n.post_number
			} else if ([TYPES.BADGE_EARNED].includes(n.notification_type)) {
				return this.discourseUrl + '/badges/' + n.data.badge_id + '/' + n.data.badge_slug + '?username=' + n.data.username
			} else if ([TYPES.MODERATOR_OR_ADMIN_INBOX].includes(n.notification_type)) {
				return this.discourseUrl + '/u/' + this.username + '/messages'
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
			} else if ([TYPES.MODERATOR_OR_ADMIN_INBOX].includes(n.notification_type)) {
				return generateUrl('/apps/integration_discourse/avatar?') + 'username=system'
			}
			return ''
		},
		getNotificationTypeImage(n) {
			if ([TYPES.PRIVATE_MESSAGE].includes(n.notification_type)) {
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
			} else if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX) {
				return generateUrl('/svg/integration_discourse/group?color=ffffff')
			}
			return generateUrl('/svg/core/actions/sound?color=' + this.themingColor)
		},
		getDisplayAndOriginalUsername(n) {
			if (n.data.display_username && n.data.display_username !== n.data.original_username) {
				return n.data.display_username + ' (@' + n.data.original_username + ')'
			} else {
				return '@' + n.data.display_username
			}
		},
		getSubline(n) {
			if ([TYPES.PRIVATE_MESSAGE, TYPES.MENTION, TYPES.LIKE, TYPES.REPLY, TYPES.REPLY_2].includes(n.notification_type)) {
				return this.getDisplayAndOriginalUsername(n)
			} else if (n.notification_type === TYPES.SOLVED) {
				return '@' + n.data.display_username
			} else if (n.notification_type === TYPES.BADGE_EARNED) {
				return n.data.badge_name
			} else if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX) {
				return ''
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
			} else if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX) {
				return '!'
			}
			return ''
		},
		getTargetTitle(n) {
			if (n.notification_type === TYPES.MODERATOR_OR_ADMIN_INBOX && n.data && n.data.inbox_count && n.data.group_name) {
				if (n.data.group_name === 'admins') {
					return this.n('integration_discourse', '{nb} item in your admins inbox', '{nb} items in your admins inbox', n.data.inbox_count, { nb: n.data.inbox_count })
				} else if (n.data.group_name === 'moderators') {
					return this.n('integration_discourse', '{nb} item in your moderators inbox', '{nb} items in your moderators inbox', n.data.inbox_count, { nb: n.data.inbox_count })
				}
			}
			return n.fancy_title
		},
		getFormattedDate(n) {
			return moment(n.created_at).locale(this.locale).format('LLL')
		},
	},
}
</script>

<style scoped lang="scss">
::v-deep .connect-button {
	margin-top: 10px;
}
</style>
