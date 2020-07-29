<template>
    <div id="discourse_prefs" class="section">
            <h2>
                <a class="icon icon-discourse"></a>
                {{ t('discourse', 'Discourse') }}
            </h2>
            <p class="settings-hint">
                {{ t('discourse', 'If you fail getting access to your Discourse account, this is probably because your Discourse instance is not authorized to give API keys to your Nextcloud instance.') }}
                <br/>
                {{ t('discourse', 'Ask the Discourse admin to change the') }}
                <br/>
                <b>"allowed_user_api_auth_redirects"</b>
                <br/>
                {{ t('discourse', 'setting. Adding') }}
                <br/>
                <b>"*"</b>
                {{ t('discourse', 'or') }}
                <b>"{{ redirect_uri }}"</b>
                <br/>
                {{ t('discourse', 'as an authorized redirection URL for authentication will allow Nextcloud to authenticate.') }}
            </p>
            <div class="discourse-grid-form">
                <label for="discourse-url">
                    <a class="icon icon-link"></a>
                    {{ t('discourse', 'Discourse instance address') }}
                </label>
                <input id="discourse-url" type="text" v-model="state.url" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('discourse', 'Discourse instance address')"/>
                <button id="discourse-oauth" v-if="showOAuth" @click="onOAuthClick">
                    <span class="icon icon-external"/>
                    {{ t('discourse', 'Request Discourse access') }}
                </button>
                <span v-else></span>
                <label for="discourse-token">
                    <a class="icon icon-category-auth"></a>
                    {{ t('discourse', 'Discourse API-key') }}
                </label>
                <input id="discourse-token" type="password" v-model="state.token" @input="onInput"
                    :readonly="readonly"
                    @focus="readonly = false"
                    :placeholder="t('discourse', 'my-api-key')"/>
            </div>
    </div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
    name: 'PersonalSettings',

    props: [],
    components: {
    },

    mounted() {
        const paramString = window.location.search.substr(1)
        const urlParams = new URLSearchParams(paramString)
        const dscToken = urlParams.get('discourseToken')
        if (dscToken === 'success') {
            showSuccess(t('discourse', 'Discourse API-key successfully retrieved!'))
        } else if (dscToken === 'error') {
            showError(t('discourse', 'Discourse API-key could not be obtained:') + ' ' + urlParams.get('message'))
        }
    },

    data() {
        return {
            state: loadState('discourse', 'user-config'),
            readonly: true,
            redirect_uri: OC.getProtocol() + '://' + OC.getHostName() + generateUrl('/apps/discourse/oauth-redirect')
        }
    },

    watch: {
    },

    computed: {
        showOAuth() {
            return this.state.url
        },
    },

    methods: {
        onInput() {
            const that = this
            delay(function() {
                that.saveOptions()
            }, 2000)()
        },
        saveOptions() {
            if (this.state.url !== '' && !this.state.url.startsWith('https://')) {
                if (this.state.url.startsWith('http://')) {
                    this.state.url = this.state.url.replace('http://', 'https://')
                } else {
                    this.state.url = 'https://' + this.state.url
                }
            }
            const req = {
                values: {
                    token: this.state.token,
                    url: this.state.url
                }
            }
            const url = generateUrl('/apps/discourse/config')
            axios.put(url, req)
                .then(function (response) {
                    showSuccess(t('discourse', 'Discourse options saved.'))
                })
                .catch(function (error) {
                    showError(t('discourse', 'Failed to save Discourse options') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        },
        onOAuthClick() {
            const redirect_endpoint = generateUrl('/apps/discourse/oauth-redirect')
            const redirect_uri = OC.getProtocol() + '://' + OC.getHostName() + redirect_endpoint
            const nonce = this.makeNonce(16)
            const request_url = this.state.url + '/user-api-key/new?client_id=' + encodeURIComponent(this.state.client_id) +
                '&auth_redirect=' + encodeURIComponent(redirect_uri) +
                '&application_name=' + encodeURIComponent('Nextclouddiscourseintegration') +
                '&nonce=' + encodeURIComponent(nonce) +
                '&public_key=' + encodeURIComponent(this.state.public_key) +
                '&scopes=' + encodeURIComponent('read,write,notifications')

            const req = {
                values: {
                    nonce: nonce,
                }
            }
            const url = generateUrl('/apps/discourse/config')
            axios.put(url, req)
                .then(function (response) {
                    window.location.replace(request_url)
                })
                .catch(function (error) {
                    showError(t('discourse', 'Failed to save Discourse nonce') +
                        ': ' + error.response.request.responseText
                    )
                })
                .then(function () {
                })
        },
        makeNonce(l) {
            let text = ''
            var chars = 'abcdefghijklmnopqrstuvwxyz0123456789'
            for (let i=0; i < l; i++) {
                text += chars.charAt(Math.floor(Math.random() * chars.length));
            }
            return text
        },
    }
}
</script>

<style scoped lang="scss">
.discourse-grid-form label {
    line-height: 38px;
}
.discourse-grid-form input {
    width: 100%;
}
.discourse-grid-form {
    width: 900px;
    display: grid;
    grid-template: 1fr / 233px 233px 300px;
    margin-left: 30px;
    button .icon {
        margin-bottom: -1px;
    }
}
#discourse_prefs .icon {
    display: inline-block;
    width: 32px;
}
#discourse_prefs .grid-form .icon {
    margin-bottom: -3px;
}
.icon-discourse {
    background-image: url(./../../img/app-dark.svg);
    background-size: 23px 23px;
    height: 23px;
    margin-bottom: -4px;
}
body.dark .icon-discourse {
    background-image: url(./../../img/app.svg);
}
</style>
