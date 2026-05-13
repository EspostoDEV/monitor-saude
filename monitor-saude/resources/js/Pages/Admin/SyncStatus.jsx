import React from 'react';
import { Head, router, usePage } from '@inertiajs/react';
import { RefreshCcw, CheckCircle, AlertTriangle, Database, ArrowLeft } from 'lucide-react';

export default function SyncStatus({ status }) {
    const { flash } = usePage().props;

    const handleSync = (disease) => {
        router.post(route('admin.sync.trigger'), { disease }, {
            onSuccess: () => {
                // Feedback visual via flash message
            }
        });
    };

    return (
        <div className="min-h-screen bg-slate-50 text-slate-900 p-8 font-sans">
            <Head title="Admin - Status de Sincronização" />

            <div className="max-w-4xl mx-auto">
                <header className="mb-12 flex justify-between items-center">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight text-slate-900 flex items-center gap-3">
                            <Database className="w-8 h-8 text-indigo-600" />
                            Saúde do Sistema
                        </h1>
                        <p className="text-slate-500 mt-2">Monitore e gerencie a integridade dos dados epidemiológicos.</p>
                    </div>
                    <button 
                        onClick={() => window.history.back()}
                        className="flex items-center gap-2 text-slate-500 hover:text-slate-900 transition-colors"
                    >
                        <ArrowLeft className="w-4 h-4" />
                        Voltar
                    </button>
                </header>

                {flash?.message && (
                    <div className="mb-6 p-4 bg-indigo-50 border border-indigo-100 text-indigo-700 rounded-xl flex items-center gap-3 animate-in fade-in slide-in-from-top-4 duration-300">
                        <CheckCircle className="w-5 h-5" />
                        {flash.message}
                    </div>
                )}

                <div className="grid grid-cols-1 md:grid-cols-3 gap-6">
                    {['dengue', 'chikungunya', 'zika'].map((diseaseName) => {
                        const item = status.find(s => s.disease === diseaseName) || {
                            disease: diseaseName,
                            last_sync: 'Nunca',
                            total_records: '0',
                            is_fresh: false
                        };

                        return (
                            <div key={diseaseName} className="bg-white rounded-2xl p-6 shadow-sm border border-slate-200 hover:shadow-md transition-shadow">
                                <div className="flex justify-between items-start mb-4">
                                    <h2 className="capitalize font-bold text-lg text-slate-800">{diseaseName}</h2>
                                    {item.is_fresh ? (
                                        <CheckCircle className="w-5 h-5 text-emerald-500" />
                                    ) : (
                                        <AlertTriangle className="w-5 h-5 text-amber-500" />
                                    )}
                                </div>

                                <div className="space-y-4">
                                    <div>
                                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Última Carga</p>
                                        <p className="text-slate-700 font-medium">{item.last_sync}</p>
                                    </div>

                                    <div>
                                        <p className="text-xs font-semibold text-slate-400 uppercase tracking-wider">Total de Registros</p>
                                        <p className="text-slate-700 font-medium">{item.total_records}</p>
                                    </div>

                                    <button
                                        onClick={() => handleSync(diseaseName)}
                                        className="w-full mt-4 flex items-center justify-center gap-2 py-2.5 px-4 bg-slate-900 text-white rounded-xl hover:bg-slate-800 transition-colors text-sm font-semibold group"
                                    >
                                        <RefreshCcw className="w-4 h-4 group-hover:rotate-180 transition-transform duration-500" />
                                        Sincronizar Agora
                                    </button>
                                </div>
                            </div>
                        );
                    })}
                </div>

                <footer className="mt-12 pt-8 border-t border-slate-200">
                    <div className="flex items-center gap-2 text-slate-400 text-sm">
                        <div className="w-2 h-2 rounded-full bg-emerald-500"></div>
                        <span>Sistema operando com PostGIS v3.5</span>
                    </div>
                </footer>
            </div>
        </div>
    );
}
