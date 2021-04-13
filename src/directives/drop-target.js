/*
 * Copyright (c) 2021. The Nextcloud Bookmarks contributors.
 *
 * This file is licensed under the Affero General Public License version 3 or later. See the COPYING file.
 */

export default {
	bind(el, binding) {
		const allowDrop = binding.value.allow
		const drop = binding.value.drop
		let dropTargetEntered = 0
		el.addEventListener('dragenter', (e) => {
			if (allowDrop(e)) {
				e.preventDefault()
				// for every descendant of this element, increment
				// when this is 0, the dropTarget class is removed
				dropTargetEntered++
				e.dataTransfer.dropEffect = 'move'
				if (dropTargetEntered === 1) {
					el.classList.add('dropTarget')
				}
			}
		})
		el.addEventListener('dragover', (e) => {
			if (allowDrop()) {
				e.preventDefault()
			}
		})
		el.addEventListener('dragleave', (e) => {
			// for every descendant of this element, decrement
			// when this is 0, the dropTarget class is removed
			dropTargetEntered = Math.max(0, dropTargetEntered - 1)
			if (dropTargetEntered === 0) {
				el.classList.remove('dropTarget')
			}
		})
		el.addEventListener('drop', (e) => {
			dropTargetEntered = 0
			drop(e)
			el.classList.remove('dropTarget')
		})
	},
}
