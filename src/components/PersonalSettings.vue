<template>
	<div id="discourse_prefs" class="section">
		<h2>
			<DiscourseIcon />
			{{ t('integration_discourse', 'Discourse integration') }}
		</h2>
		<p v-if="!connected" class="settings-hint">
			{{ t('integration_discourse', 'If you fail getting access to your Discourse account, this is probably because your Discourse instance is not authorized to give API keys to your Nextcloud instance.') }}
			<br>
			{{ t('integration_discourse', 'Ask the Discourse admin to add this URI to the "allowed_user_api_auth_redirects" list in admin settings:') }}
			<br><b>"web+nextclouddiscourse://auth-redirect"</b>
		</p>
		<br>
		<p v-if="!connected" class="settings-hint line">
			<InformationOutlineIcon :size="20" />
			{{ t('integration_discourse', 'Make sure you accepted the protocol registration on top of this page if you want to authenticate to Discourse.') }}
		</p>
		<p v-if="!connected" class="settings-hint">
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
			<CheckboxRadioSwitch
				:checked="state.navigation_enabled"
				@update:checked="onCheckboxChanged($event, 'navigation_enabled')">
				{{ t('integration_discourse', 'Enable navigation link') }}
			</CheckboxRadioSwitch>
			<div class="line">
				<label for="discourse-url">
					<EarthIcon :size="20" />
					{{ t('integration_discourse', 'Discourse instance address') }}
				</label>
				<input id="discourse-url"
					v-model="state.url"
					type="text"
					:disabled="connected === true"
					:placeholder="t('integration_discourse', 'Discourse instance address')"
					@input="onInput">
			</div>
			<NcButton v-if="showOAuth"
				id="discourse-oauth"
				:class="{ loading: loading }"
				:disabled="loading === true"
				@click="onOAuthClick">
				<template #icon>
					<OpenInNewIcon />
				</template>
				{{ t('integration_discourse', 'Connect to Discourse') }}
			</NcButton>
			<div v-if="connected" class="line">
				<label class="discourse-connected">
					<CheckIcon :size="20" />
					{{ t('integration_discourse', 'Connected as {username}', { username: state.user_name }) }}
				</label>
				<NcButton @click="onLogoutClick">
					<template #icon>
						<CloseIcon />
					</template>
					{{ t('integration_discourse', 'Disconnect from Discourse') }}
				</NcButton>
			</div>
			<br>
			<div v-if="connected" id="discourse-search-block">
				<CheckboxRadioSwitch
					:checked="state.search_topics_enabled"
					@update:checked="onCheckboxChanged($event, 'search_topics_enabled')">
					{{ t('integration_discourse', 'Enable unified search for topics') }}
				</CheckboxRadioSwitch>
				<CheckboxRadioSwitch
					:checked="state.search_posts_enabled"
					@update:checked="onCheckboxChanged($event, 'search_posts_enabled')">
					{{ t('integration_discourse', 'Enable searching for posts') }}
				</CheckboxRadioSwitch>
				<br>
				<p v-if="state.search_topics_enabled || state.search_posts_enabled" class="settings-hint line">
					<InformationOutlineIcon :size="20" />
					{{ t('integration_discourse', 'Warning, everything you type in the search bar will be sent to your Discourse instance.') }}
				</p>
			</div>
		</div>
	</div>
</template>

<script>
import EarthIcon from 'vue-material-design-icons/Earth.vue'
import InformationOutlineIcon from 'vue-material-design-icons/InformationOutline.vue'
import OpenInNewIcon from 'vue-material-design-icons/OpenInNew.vue'
import CheckIcon from 'vue-material-design-icons/Check.vue'
import CloseIcon from 'vue-material-design-icons/Close.vue'
import NcButton from '@nextcloud/vue/dist/Components/Button.js'
import CheckboxRadioSwitch from '@nextcloud/vue/dist/Components/CheckboxRadioSwitch.js'
import { loadState } from '@nextcloud/initial-state'
import { generateUrl, imagePath } from '@nextcloud/router'
import axios from '@nextcloud/axios'
import { delay, detectBrowser } from '../utils.js'
import { showSuccess, showError } from '@nextcloud/dialogs'
import DiscourseIcon from './icons/DiscourseIcon.vue'

const browser = detectBrowser()

export default {
	name: 'PersonalSettings',

	components: {
		DiscourseIcon,
		NcButton,
		CheckboxRadioSwitch,
		OpenInNewIcon,
		CloseIcon,
		InformationOutlineIcon,
		EarthIcon,
		CheckIcon,
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
			isChromium: browser === 'chrome',
			isFirefox: browser === 'firefox',
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
		onCheckboxChanged(newValue, key) {
			this.state[key] = newValue
			this.saveOptions({ [key]: this.state[key] ? '1' : '0' })
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
#discourse_prefs {
	h2 {
		display: flex;
		align-items: center;
		.discourse-icon {
			margin-right: 8px;
		}
	}

	.line {
		display: flex;
		align-items: center;
		> label {
			width: 300px;
			display: flex;
			align-items: center;
			span {
				margin-right: 4px;
			}
		}
		> input {
			width: 250px;
		}
		&.settings-hint {
			span {
				margin-right: 4px;
			}
		}
	}

	#discourse-content {
		margin-left: 40px;
	}
}
</style>
