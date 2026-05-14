import React from 'react';

export default function AdminFooter() {
    return (
        <footer className="mt-12 py-8 border-t border-gray-800 flex flex-col md:flex-row justify-between items-center gap-4 text-xs text-gray-500 font-medium">
            <div className="flex items-center gap-6">
                <div className="flex items-center gap-2">
                    <div className="w-2 h-2 rounded-full bg-green-500 shadow-[0_0_8px_rgba(34,197,94,0.5)]"></div>
                    <span>API InfoDengue Online</span>
                </div>
                <span>PostGIS v3.5</span>
            </div>
            <div>MonitorSaúde Alpha v2.0 • 2026</div>
        </footer>
    );
}
