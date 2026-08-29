export function toFormData(payload) {
    const form = new FormData()

    Object.entries(payload).forEach(([key, value]) => {
        if (value === undefined || value === null) {
            return
        }

        if (typeof value === 'boolean') {
            // Laravel's `boolean` validation rule only accepts "1"/"0" (plus true/false/1/0
            // themselves), not the literal strings "true"/"false" that FormData would
            // otherwise coerce a JS boolean into.
            form.append(key, value ? '1' : '0')
        } else if (typeof value === 'object' && !(value instanceof File)) {
            form.append(key, JSON.stringify(value))
        } else {
            form.append(key, value)
        }
    })

    return form
}
