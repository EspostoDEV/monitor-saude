/**
 * Epidemiological utility functions and constants.
 */

export const ALERT_LEVELS = {
    CRITICAL: 4,
    HIGH: 3,
    MODERATE: 2,
    LOW: 1,
    UNKNOWN: 0
};

/**
 * Returns a color for a given alert level.
 * 
 * @param {number} level 
 * @param {number} opacity 
 * @returns {string}
 */
export const getLevelColor = (level, opacity = 0.6) => {
    switch (level) {
        case ALERT_LEVELS.CRITICAL:
            return `rgba(239, 68, 68, ${opacity})`; // Red
        case ALERT_LEVELS.HIGH:
            return `rgba(249, 115, 22, ${opacity})`; // Orange
        case ALERT_LEVELS.MODERATE:
            return `rgba(234, 179, 8, ${opacity})`; // Yellow
        case ALERT_LEVELS.LOW:
            return `rgba(34, 197, 94, ${opacity})`; // Green
        default:
            return `rgba(71, 85, 105, ${opacity})`; // Slate
    }
};

/**
 * Returns a label for a given alert level.
 * 
 * @param {number} level 
 * @returns {string}
 */
export const getLevelLabel = (level) => {
    switch (level) {
        case ALERT_LEVELS.CRITICAL: return '⚠️ Surto Crítico';
        case ALERT_LEVELS.HIGH: return '🟠 Transmissão Alta';
        case ALERT_LEVELS.MODERATE: return '🟡 Transmissão Moderada';
        case ALERT_LEVELS.LOW: return '✅ Circulação Estável';
        default: return '⚪ Sem Dados';
    }
};
