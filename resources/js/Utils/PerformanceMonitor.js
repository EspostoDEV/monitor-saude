/**
 * MonitorSaúde Performance Monitoring Utility
 */
const PerformanceMonitor = {
    startTimes: new Map(),

    init() {
        if (typeof window === 'undefined') return;

        // Monitor main application load
        const pageStart = window.performance.timing?.navigationStart || Date.now();
        
        window.addEventListener('load', () => {
            const loadTime = Date.now() - pageStart;
            this.log('Application Load', `${loadTime.toFixed(2)}ms`);
        });

        // Monitor Resource Timing
        if ('PerformanceObserver' in window) {
            const observer = new PerformanceObserver((list) => {
                list.getEntries().forEach((entry) => {
                    if (entry.entryType === 'resource') {
                        const name = entry.name.split('/').pop() || entry.name;
                        this.log(name, `${entry.duration.toFixed(2)}ms`);
                    }
                });
            });
            observer.observe({ entryTypes: ['resource'] });
        }

        this.log('Monitor Init', 'OK');
    },

    log(label, duration) {
        console.log(`%c[PERF] ⚡ ${label} %c${duration}`, 'color: #10b981; font-weight: bold;', 'color: #94a3b8;');
    }
};

export default PerformanceMonitor;
