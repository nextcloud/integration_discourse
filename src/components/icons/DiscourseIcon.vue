<!--
  - SPDX-FileCopyrightText: 2020 Nextcloud GmbH and Nextcloud contributors
  - SPDX-License-Identifier: AGPL-3.0-or-later
-->

<template>
	<span :aria-hidden="!title"
		:aria-label="title"
		class="material-design-icon discourse-icon"
		role="img"
		v-bind="$attrs"
		@click="$emit('click', $event)">
		<svg
			:fill="fillColor"
			:width="size"
			:height="size"
			enable-background="new 0 0 106 106"
			version="1.1"
			viewBox="0 0 106 106"
			xml:space="preserve"
			xmlns="http://www.w3.org/2000/svg">
			<g transform="translate(2,0)">
				<path d="m51.869 1c-28.16 0-51.869 22.83-51.869 51v52.811l51.859-0.05078c28.16 0 51-23.709 51-51.869s-22.86-51.891-50.99-51.891zm0.5 19.74h0.03125a31.61 31.61 0 1 1-13 60.43l-20.541 4.6406 5.7207-18.4a31.62 31.62 0 0 1 27.789-46.67z" />
				<path class="cls-3" d="m77.45 32.12a31.6 31.6 0 0 1-38.05 48l-20.54 4.7 20.91-2.47a31.6 31.6 0 0 0 37.68-50.23z" fill="#323232" />
				<path class="cls-4" d="M71.63,26.29A31.6,31.6,0,0,1,38.8,78L18.86,84.82,39.4,80.17A31.6,31.6,0,0,0,71.63,26.29Z" fill="#646464" />
				<path class="cls-5" d="m26.47 67.11a31.61 31.61 0 0 1 51-35 31.61 31.61 0 0 0-52.89 34.3l-5.72 18.4z" fill="#646464" />
				<path class="cls-6" d="m24.58 66.41a31.61 31.61 0 0 1 47.05-40.12 31.61 31.61 0 0 0-49 39.63l-3.76 18.9z" fill="#323232" />
			</g>
		</svg>
	</span>
</template>

<script>
export default {
	name: 'DiscourseIcon',
	props: {
		title: {
			type: String,
			default: '',
		},
		fillColor: {
			type: String,
			default: 'currentColor',
		},
		size: {
			type: Number,
			default: 24,
		},
	},
}
</script>
