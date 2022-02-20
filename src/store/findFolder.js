/**
 * @param id
 * @param children
 */
export function findFolder(id, children) {
	if (!children) {
		return []
	} else if (Number(id) === -1) {
		return [{ id: -1, children }]
	} else if (!children.length) {
		return []
	}
	const folders = children.filter(folder => Number(folder.id) === Number(id))
	if (folders.length) {
		return folders
	} else {
		for (const child of children) {
			const folders = findFolder(id, child.children)
			if (folders.length) {
				folders.push(child)
				return folders
			}
		}
		return []
	}
}
