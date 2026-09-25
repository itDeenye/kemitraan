import { onMounted, onUnmounted } from 'vue';

/**
 * Sets a CSS custom property `--vh` on the document root that tracks the real
 * visible viewport height. On modern browsers this equals `1dvh`; on older
 * browsers that lack Dynamic Viewport units support it falls back to
 * `window.innerHeight / 100`.
 *
 * Usage in CSS:
 *   height: calc(var(--vh, 1vh) * 100);   // replaces `height: 100vh`
 *
 * The value updates on `resize` and `orientationchange` events so the layout
 * adapts when mobile browser chrome (address bar / toolbar) appears or hides.
 */
export function useViewportHeight(): void {
    const supportsDvh = CSS.supports?.('height', '100dvh') ?? false;

    function setVh(): void {
        if (supportsDvh) {
            document.documentElement.style.setProperty('--vh', '1dvh');
        } else {
            const vh = window.innerHeight * 0.01;
            document.documentElement.style.setProperty('--vh', `${vh}px`);
        }
    }

    onMounted(() => {
        setVh();

        if (!supportsDvh) {
            window.addEventListener('resize', setVh, { passive: true });
            window.addEventListener('orientationchange', setVh, { passive: true });
        }
    });

    onUnmounted(() => {
        if (!supportsDvh) {
            window.removeEventListener('resize', setVh);
            window.removeEventListener('orientationchange', setVh);
        }
    });
}
