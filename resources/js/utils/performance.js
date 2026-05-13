/**
 * PerformanceMonitor.js
 * Utilitário para monitorar e logar o tempo de carregamento de recursos no console.
 */
class PerformanceMonitor {
    static init() {
        if (typeof window === 'undefined' || !window.PerformanceObserver) {
            return;
        }

        // Observer para recursos (JS, CSS, Imagens, Fetch)
        const observer = new PerformanceObserver((list) => {
            list.getEntries().forEach((entry) => {
                // Filtra recursos muito rápidos (< 10ms) para evitar ruído
                if (entry.duration < 10) return;

                const duration = entry.duration.toFixed(2);
                let color = '#4B5563'; // Slate-600 default

                if (entry.duration > 500) color = '#EF4444'; // Red-500 (Muito lento)
                else if (entry.duration > 200) color = '#F59E0B'; // Amber-500 (Lento)
                else if (entry.duration > 50) color = '#10B981'; // Emerald-500 (Rápido)

                console.log(
                    `%c[PERF] %c⚡ %c${entry.name.split('/').pop()} %c${duration}ms`,
                    'color: #6366F1; font-weight: bold;', // [PERF] em Indigo
                    'color: #F59E0B;', // Raio em Amber
                    'color: #1F2937; font-weight: 500;', // Nome do arquivo
                    `color: ${color}; font-weight: bold;` // Duração colorida
                );
            });
        });

        try {
            observer.observe({ type: 'resource', buffered: true });
        } catch (e) {
            console.warn('[PERF] PerformanceObserver não suporta o tipo "resource".');
        }
    }
}

export default PerformanceMonitor;
