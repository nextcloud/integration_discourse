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
			<br><br>
			<span class="icon icon-details" />
			{{ t('integration_discourse', 'Make sure you accepted the protocol registration on top of this page if you want to authenticate to Discourse.') }}
			<span v-if="isChromium">
				<br>
				{{ t('integration_discourse', 'With Chrome/Chromium, you should see a popup on browser top-left to authorize this page to open "web+nextclouddiscourse" links.') }}
				<br>
				{{ t('integration_discourse', 'If you don\'t see the popup, you can still click on this icon in the address bar.') }}
				<br>
				<img :src="chromiumImagePath">
				<br>
				{{ t('integration_discourse', 'Then authorize this page to open "web+nextclouddiscourse" links.') }}
				<br>
				{{ t('integration_discourse', 'If you still don\'t manage to get the protocol registered, check your settings on this page:') }}
				<b>chrome://settings/handlers</b>
			</span>
			<span v-else-if="isFirefox">
				<br>
				{{ t('integration_discourse', 'With Firefox, you should see a bar on top of this page to authorize this page to open "web+nextclouddiscourse" links.') }}
				<br><br>
				<img :src="firefoxImagePath">
			</span>
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
			</div>
			<Button v-if="showOAuth"
				id="discourse-oauth"
				:class="{ loading: loading }"
				:disabled="loading === true"
				@click="onOAuthClick">
				<template #icon>
					<OpenInNewIcon />
				</template>
				{{ t('integration_discourse', 'Connect to Discourse') }}
			</Button>
			<div v-if="connected" class="discourse-grid-form">
				<label class="discourse-connected">
					<a class="icon icon-checkmark-color" />
					{{ t('integration_discourse', 'Connected as {username}', { username: state.user_name }) }}
				</label>
				<Button id="discourse-rm-cred" @click="onLogoutClick">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('integration_discourse', 'Disconnect from Discourse') }}
				</Button>
			</div>
			<br>
			<div v-if="connected" id="discourse-search-block">
				<input
					id="search-discourse-topics"
					type="checkbox"
					class="checkbox"
					:checked="state.search_topics_enabled"
					@input="onSearchTopicsChange">
				<label for="search-discourse-topics">{{ t('integration_discourse', 'Enable searching for topics') }}</label>
				<br><br>
				<input
					id="search-discourse-posts"
					type="checkbox"
					class="checkbox"
					:checked="state.search_posts_enabled"
					@input="onSearchPostsChange">
				<label for="search-discourse-posts">{{ t('integration_discourse', 'Enable searching for posts') }}</label>
				<br><br>
				<p v-if="state.search_topics_enabled || state.search_posts_enabled" class="settings-hint">
					<span class="icon icon-details" />
					{{ t('integration_discourse', 'Warning, everything you type in the search bar will be sent to your Discourse instance.') }}
				</p>
			</div>
		</div>
	</div>
</template>

<script>
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew'
import CloseIcon from 'vue-material-design-icons/Close'
import Button from '@nextcloud/vue/dist/Components/Button'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, detectBrowser } from '../utils'
import { showSuccess, showError } from '@nextcloud/dialogs'
import '@nextcloud/dialogs/styles/toast.scss'

export default {
	name: 'PersonalSettings',

	components: {
		Button,
		OpenInNewIcon,
		CloseIcon,
	},

	props: [],

	data() {
		return {
			state: loadState('integration_discourse', 'user-config'),
			loading: false,
			// TODO choose between classic redirection (requires 'allowed user api auth redirects' => * or the specific redirect_uri)
			// and protocol handler based redirection for which 'allowed user api auth redirects' => web+nextclouddiscourse:// is enough and will work with all NC instances
			// redirect_uri: OC.getProtocol() + '://' + OC.getHostName() + generateUrl('/apps/integration_discourse/oauth-redirect'),
			redirect_uri: 'web+nextclouddiscourse://auth-redirect',
			chromiumImagePath: imagePath('integration_discourse', 'chromium.png'),
			firefoxImagePath: imagePath('integration_discourse', 'firefox.png'),
			isChromium: detectBrowser() === 'chrome',
			isFirefox: detectBrowser() === 'firefox',
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
			showSuccess(t('integration_discourse', 'Successfully connected to Discourse!'))
		} else if (dscToken === 'error') {
			showError(t('integration_discourse', 'Discourse API-key could not be obtained:') + ' ' + urlParams.get('message'))
		}

		// register protocol handler
		if (window.isSecureContext && window.navigator.registerProtocolHandler) {
			const ncUrl = window.location.protocol
				+ '//' + window.location.hostname
				+ window.location.pathname.replace('settings/user/connected-accounts', '').replace('/index.php/', '')
			window.navigator.registerProtocolHandler(
				'web+nextclouddiscourse',
				generateUrl('/apps/integration_discourse/oauth-protocol-redirect') + '?url=%s',
				t('integration_discourse', 'Nextcloud Discourse integration on {ncUrl}', { ncUrl })
			)
		}
	},

	methods: {
		onSearchTopicsChange(e) {
			this.state.search_topics_enabled = e.target.checked
			this.saveOptions({ search_topics_enabled: this.state.search_topics_enabled ? '1' : '0' })
		},
		onSearchPostsChange(e) {
			this.state.search_posts_enabled = e.target.checked
			this.saveOptions({ search_posts_enabled: this.state.search_posts_enabled ? '1' : '0' })
		},
		onLogoutClick() {
			this.state.token = ''
			this.saveOptions({ token: this.state.token, url: this.state.url })
		},
		onInput() {
			this.loading = true
			if (this.state.url !== '' && !this.state.url.startsWith('https://')) {
				if (this.state.url.startsWith('http://')) {
					this.state.url = this.state.url.replace('http://', 'https://')
				} else {
					this.state.url = 'https://' + this.state.url
				}
			}
			delay(() => {
				const pattern = /^(https?:\/\/)?[A-Za-z0-9]+\.[A-Za-z0-9].*/
				if (pattern.test(this.state.url)) {
					this.saveOptions({ url: this.state.url })
				} else {
					showError(t('integration_discourse', 'Discourse URL is invalid'))
					this.loading = false
				}
			}, 2000)()
		},
		saveOptions(values) {
			const req = {
				values,
			}
			const url = generateUrl('/apps/integration_discourse/config')
			axios.put(url, req)
				.then((response) => {
					showSuccess(t('integration_discourse', 'Discourse options saved'))
				})
				.catch((error) => {
					showError(
						t('integration_discourse', 'Failed to save Discourse options')
						+ ': ' + error.response.request.responseText
					)
				})
				.then(() => {
					this.loading = false
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
			if (window.isSecureContext && window.crypto && window.crypto.getRandomValues) {
				return this.makeSecureNonce(l)
			} else {
				return this.makeSimpleNonce(l)
			}
		},
		makeSecureNonce(l) {
			const charset = '0123456789ABCDEFGHIJKLMNOPQRSTUVXYZabcdefghijklmnopqrstuvwxyz-._~'
			const result = []
			window.crypto.getRandomValues(new Uint8Array(l)).forEach((c) => {
				result.push(charset[c % charset.length])
			})
			return result.join('')
		},
		makeSimpleNonce(l) {
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
	max-width: 600px;
	display: grid;
	grid-template: 1fr / 1fr 1fr;
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
	filter: var(--background-invert-if-dark);
}

body.theme--dark .icon-discourse {
	background-image: url(./../../img/app.svg);
}

#discourse-content {
	margin-left: 40px;
}

</style>
