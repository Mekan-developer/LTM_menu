// Короткие сигналы без аудиофайлов (Web Audio). Разные звуки для разных событий.
let ctx = null;

function tone(freq, start, duration, gain = 0.18) {
    ctx ??= new (window.AudioContext || window.webkitAudioContext)();
    const osc = ctx.createOscillator();
    const amp = ctx.createGain();
    osc.type = 'sine';
    osc.frequency.value = freq;
    amp.gain.setValueAtTime(0, ctx.currentTime + start);
    amp.gain.linearRampToValueAtTime(gain, ctx.currentTime + start + 0.01);
    amp.gain.exponentialRampToValueAtTime(
        0.0001,
        ctx.currentTime + start + duration,
    );
    osc.connect(amp).connect(ctx.destination);
    osc.start(ctx.currentTime + start);
    osc.stop(ctx.currentTime + start + duration + 0.02);
}

export function useSound() {
    return {
        newOrder: () => {
            try {
                tone(880, 0, 0.18);
                tone(1175, 0.2, 0.22);
            } catch {
                /* звук заблокирован браузером */
            }
        },
        ready: () => {
            try {
                tone(1320, 0, 0.09, 0.14);
            } catch {
                /* */
            }
        },
    };
}
