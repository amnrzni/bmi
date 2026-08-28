/*
 | Signature pad for the Merdeka judging screen.
 |
 | Exposed as a global factory rather than registered with Alpine.data(), so the
 | blade can call it straight from x-data without this file needing a handle on
 | Alpine — Livewire brings its own.
 |
 | Alpine re-initialises this when the scoresheet is inserted into the DOM, which
 | is the whole reason it isn't a one-shot @script: the judge moves between the
 | corner list and the sheet without a page load.
 */
window.merdekaSignaturePad = () => ({
    signed: false,
    drawing: false,
    ctx: null,

    init() {
        this.canvas = this.$refs.pad
        this.setup()

        // A rotate or a keyboard opening resizes the canvas, which clears it.
        // Only re-fit while the pad is still blank — silently wiping a finished
        // signature is exactly the failure the prototype had.
        this.onResize = () => {
            if (!this.signed) this.setup()
        }
        window.addEventListener('resize', this.onResize)
    },

    destroy() {
        window.removeEventListener('resize', this.onResize)
    },

    setup() {
        const ratio = window.devicePixelRatio || 1
        const width = this.canvas.parentElement.clientWidth

        this.canvas.width = width * ratio
        this.canvas.height = 160 * ratio

        this.ctx = this.canvas.getContext('2d')
        this.ctx.scale(ratio, ratio)
        // Opaque background: the PNG is shown on a dark results screen and may
        // be printed, and a transparent signature is invisible on both.
        this.ctx.fillStyle = '#f5ede7'
        this.ctx.fillRect(0, 0, width, 160)
        this.ctx.lineWidth = 2.2
        this.ctx.lineCap = 'round'
        this.ctx.lineJoin = 'round'
        this.ctx.strokeStyle = '#241316'
    },

    point(event) {
        const box = this.canvas.getBoundingClientRect()

        return { x: event.clientX - box.left, y: event.clientY - box.top }
    },

    start(event) {
        this.drawing = true
        const at = this.point(event)
        this.ctx.beginPath()
        this.ctx.moveTo(at.x, at.y)
        this.canvas.setPointerCapture(event.pointerId)
    },

    move(event) {
        if (!this.drawing) return

        const at = this.point(event)
        this.ctx.lineTo(at.x, at.y)
        this.ctx.stroke()
        this.signed = true
    },

    end() {
        if (!this.drawing) return

        this.drawing = false
        if (this.signed) this.push()
    },

    /*
     | Third argument false: don't trigger a Livewire round trip. The property is
     | read on submit, and re-rendering here would be a network call per stroke.
     */
    push() {
        this.$wire.set('signature', this.canvas.toDataURL('image/png'), false)
    },

    clear() {
        this.setup()
        this.signed = false
        this.$wire.set('signature', '', false)
    },
})
