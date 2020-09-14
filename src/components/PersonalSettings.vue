<template>
	<div id="discourse_prefs" class="section">
		<h2>
			<a class="icon icon-discourse" />
			{{ t('integration_discourse', 'Discourse integration') }}
		</h2>
		<p v-if="!connected" class="settings-hint">
			{{ t('integration_discourse', 'If you fail getting access to your Discourse account, this is probably because your Discourse instance is not authorized to give API keys to your Nextcloud instance.') }}
			<br>
			{{ t('integration_discourse', 'Ask the Discourse admin to add this URI to the "allowed_user_api_auth_redirects" list in admin settings:') }}
			<br><b>"web+nextclouddiscourse://auth-redirect"</b>
		</p>
		<div id="discourse-content">
			<div class="discourse-grid-form">
				<label for="discourse-url">
					<a class="icon icon-link" />
					{{ t('integration_discourse', 'Discourse instance address') }}
				</label>
				<input id="discourse-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_discourse', 'Discourse instance address')"
					@input="onInput">
				<button v-if="showOAuth"
					id="discourse-oauth"
					@click="onOAuthClick">
					<span class="icon icon-external" />
					{{ t('integration_discourse', 'Connect to Discourse') }}
				</button>
				<span v-else />
			</div>
			<div v-if="connected" class="discourse-connected">
				<label>
					{{ t('integration_discourse', 'Connected as {username}', { username: state.user_name }) }}
				</label>
				<button id="discourse-rm-cred" @click="onLogoutClick">
					<span class="icon icon-close" />
					{{ t('integration_discourse', 'Disconnect from Discourse') }}
				</button>
			</div>
		</div>
	</div>
</template>

<script>
import { loadState } from '@nextcloud/initial-state'
import { generateUrl } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'

export default {
	name: 'PersonalSettings',

	components: {
	},

	props: [],

	data() {
		return {
			state: loadState('integration_discourse', 'user-config'),
			readonly: true,
			// TODO choose between classic redirection (requires 'allowed user api auth redirects' => * or the specific redirect_uri)
			// and protocol handler based redirection for which 'allowed user api auth redirects' => web+nextclouddiscourse:// is enough and will work with all NC instances
			// redirect_uri: OC.getProtocol() + '://' + OC.getHostName() + generateUrl('/apps/integration_discourse/oauth-redirect'),
			redirect_uri: 'web+nextclouddiscourse://auth-redirect',
		}
	},

	computed: {
		showOAuth() {
			return this.state.url && !this.connected
		},
		connected() {
			return this.state.token && this.state.token !== ''
		},
	},

	watch: {
	},

	mounted() {
		const paramString = window.location.search.substr(1)
		// eslint-disable-next-line
		const urlParams = new URLSearchParams(paramString)
		const dscToken = urlParams.get('discourseToken')
		if (dscToken === 'success') {
			showSuccess(t('integration_discourse', 'Discourse API-key successfully retrieved!'))
		} else if (dscToken === 'error') {
			showError(t('integration_discourse', 'Discourse API-key could not be obtained:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			window.navigator.registerProtocolHandler('web+nextclouddiscourse', generateUrl('/apps/integration_discourse/oauth-protocol-redirect') + '?url=%s', 'Nextcloud Discourse integration')
		}
	},

	methods: {
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions()
		},
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
					url: this.state.url,
				},
			}
			const url = generateUrl('/apps/integration_discourse/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_discourse', 'Discourse options saved.'))
				})
				.catch((error) => {
					showError(
						t('integration_discourse', 'Failed to save Discourse options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		onOAuthClick() {
			const nonce = this.makeNonce(16)
			const requestUrl = this.state.url + '/user-api-key/new?client_id=' + encodeURIComponent(this.state.client_id)
				+ '&auth_redirect=' + encodeURIComponent(this.redirect_uri)
				+ '&application_name=' + encodeURIComponent('Nextclouddiscourseintegration')
				+ '&nonce=' + encodeURIComponent(nonce)
				+ '&public_key=' + encodeURIComponent(this.state.public_key)
				+ '&scopes=' + encodeURIComponent('read,write,notifications')

			const req = {
				values: {
					nonce,
				},
			}
			const url = generateUrl('/apps/integration_discourse/config')
			axios.put(url, req)
				.then((response) => {
					window.location.replace(requestUrl)
				})
				.catch((error) => {
					showError(
						t('integration_discourse', 'Failed to save Discourse nonce')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
				})
		},
		makeNonce(l) {
			let text = ''
			const chars = 'abcdefghijklmnopqrstuvwxyz0123456789'
			for (let i = 0; i < l; i++) {
				text += chars.charAt(Math.floor(Math.random() * chars.length))
			}
			return text
		},
	},
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
	max-width: 900px;
	display: grid;
	grid-template: 1fr / 1fr 1fr 1fr;
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
#discourse-content {
	margin-left: 40px;
}
#discourse-rm-cred {
    margin-left: 10px;
}
.discourse-connected {
    margin-left: 35px;
}
</style>
