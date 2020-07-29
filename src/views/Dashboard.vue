<template>
    <div>
        <ul v-if="state === 'ok'" class="notification-list">
            <li v-for="n in notifications" :key="getUniqueKey(n)"
                @mouseover="$set(hovered, getUniqueKey(n), true)" @mouseleave="$set(hovered, getUniqueKey(n), false)">
                <div class="popover-container">
                    <!--Popover :open="hovered[getUniqueKey(n)]" placement="top" class="content-popover" offset="40">
                        <template>
                            <h3>{{ n.project_path }}</h3>
                            <div class="popover-author">
                                <Avatar
                                    class="author-avatar"
                                    :size="24"
                                    :url="getAuthorAvatarUrl(n)"
                                    />
                                <span class="popover-author-name">{{ getAuthorFullName(n) }}</span>
                            </div>
                            {{ getFormattedDate(n) }}<br/>
                            {{ getTargetIdentifier(n) }} {{ n.target_title }}<br/><br/>
                            <b>{{ getNotificationContent(n) }}</b><br/>
                            {{ getTargetContent(n) }}
                        </template>
                    </Popover-->
                </div>
                <a :href="getNotificationTarget(n)" target="_blank" class="notification-list__entry">
                    <Avatar
                        class="project-avatar"
                        :url="getNotificationImage(n)"
                        :user="getAuthorFullName(n)"
                        />
                    <img class="discourse-notification-icon" :src="getNotificationTypeImage(n)"/>
                    <div class="notification__details">
                        <h3>
                            {{ getTargetTitle(n) }}
                        </h3>
                        <p class="message" :title="getSubline(n)">
                            {{ getSubline(n) }}
                        </p>
                    </div>
                </a>
            </li>
        </ul>
        <div v-else-if="state === 'no-token'">
            <a :href="settingsUrl">
                {{ t('discourse', 'Click here to configure the access to your Discourse account.')}}
            </a>
        </div>
        <div v-else-if="state === 'error'">
            <a :href="settingsUrl">
                {{ t('discourse', 'Incorrect API key.') }}
                {{ t('discourse', 'Click here to configure the access to your Discourse account.')}}
            </a>
        </div>
        <div v-else-if="state === 'loading'" class="icon-loading-small"></div>
    </div>
</template>

<script>
import axios from '@nextcloud/axios'
import { generateUrl, imagePath } from '@nextcloud/router'
import { Avatar, Popover } from '@nextcloud/vue'
import { showSuccess, showError } from '@nextcloud/dialogs'
import moment from '@nextcloud/moment'
import { getLocale } from '@nextcloud/l10n'

export default {
    name: 'Dashboard',

    props: [],
    components: {
        Avatar, Popover
    },

    beforeMount() {
        this.launchLoop()
    },

    mounted() {
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
        lastDate() {
            const nbNotif = this.notifications.length
            return (nbNotif > 0) ? this.notifications[0].created_at : null
        },
        lastMoment() {
            return moment(this.lastDate)
        },
    },

    methods: {
        async launchLoop() {
            // get discourse URL first
            try {
                const response = await axios.get(generateUrl('/apps/discourse/url'))
                this.discourseUrl = response.data.replace(/\/+$/, '')
            } catch (error) {
                console.log(error)
            }
            // then launch the loop
            this.fetchNotifications()
            this.loop = setInterval(() => this.fetchNotifications(), 15000)
        },
        fetchNotifications() {
            const req = {}
            if (this.lastDate) {
                req.params = {
                    since: this.lastDate
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
                    console.log(error)
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
                    this.notifications = toAdd.concat(this.notifications).slice(0, 7)
                }
            } else {
                // first time we don't check the date
                this.notifications = this.filter(newNotifications).slice(0, 7)
            }
        },
        filter(notifications) {
            return notifications.filter((n) => {
                // type 12 is badge earned
                // TODO uncomment this
                //return (!n.read && ![12].includes(n.notification_type))
                return (![12].includes(n.notification_type))
            })
        },
        getNotificationTarget(n) {
            // private message
            if ([6, 1].includes(n.notification_type)) {
                return this.discourseUrl + '/t/' + n.slug + '/' + n.topic_id
            }
            return ''
        },
        getUniqueKey(n) {
            return n.id
        },
        getAuthorFullName(n) {
            if (n.notification_type === 6) {
                return n.data.display_username
            }
            return ''
        },
        getNotificationImage(n) {
            if ([6, 1].includes(n.notification_type)) {
                return (n.data.original_username) ?
                    generateUrl('/apps/discourse/avatar?') + encodeURIComponent('username') + '=' + encodeURIComponent(n.data.original_username) :
                    ''
            }
            return ''
        },
        getNotificationProjectName(n) {
            return n.project.path_with_namespace
        },
        getNotificationContent(n) {
            return n.fancy_title
        },
        getNotificationTypeImage(n) {
            if (n.notification_type === 6) {
                return generateUrl('/svg/core/actions/mail?color=' + this.themingColor)
            } else if (n.notification_type === 1) {
                return generateUrl('/svg/core/actions/comment?color=' + this.themingColor)
            }
            return generateUrl('/svg/core/actions/sound?color=' + this.themingColor)
        },
        getNotificationActionChar(n) {
            return ''
        },
        getSubline(n) {
            // private message
            if ([6, 1].includes(n.notification_type)) {
                if (n.data.display_username && n.data.display_username !== n.data.original_username) {
                    return n.data.display_username + '(@' + n.data.original_username + ')'
                } else {
                    return n.data.display_username
                }
            } else if (n.notification_type === 6) {
            }
            return ''
        },
        getTargetContent(n) {
            return n.body
        },
        getTargetTitle(n) {
            return n.fancy_title
        },
        getProjectPath(n) {
            return n.project.path_with_namespace
        },
        getTargetIdentifier(n) {
            return ''
        },
        getFormattedDate(n) {
            return moment(n.created_at).locale(this.locale).format('LLL')
        },
    },
}
</script>

<style scoped lang="scss">
li .notification-list__entry {
    display: flex;
    align-items: flex-start;
    padding: 8px;

    &:hover,
    &:focus {
        background-color: var(--color-background-hover);
        border-radius: var(--border-radius-large);
    }
    .project-avatar {
        position: relative;
        margin-top: auto;
        margin-bottom: auto;
    }
    .notification__details {
        padding-left: 8px;
        max-height: 44px;
        flex-grow: 1;
        overflow: hidden;
        display: flex;
        flex-direction: column;
        h3,
        .message {
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .message span {
            width: 10px;
            display: inline-block;
            margin-bottom: -3px;
        }
        h3 {
            font-size: 100%;
            margin: 0;
        }
        .message {
            width: 100%;
            color: var(--color-text-maxcontrast);
        }
    }
    img.discourse-notification-icon {
        position: absolute;
        width: 14px;
        height: 14px;
        margin: 27px 0 10px 24px;
    }
    button.primary {
        padding: 21px;
        margin: 0;
    }
}
.date-popover {
    position: relative;
    top: 7px;
}
.content-popover {
    height: 0px;
    width: 0px;
    margin-left: auto;
    margin-right: auto;
}
.popover-container {
    width: 100%;
    height: 0px;
}
.popover-author-name {
    vertical-align: top;
    line-height: 24px;
}
</style>