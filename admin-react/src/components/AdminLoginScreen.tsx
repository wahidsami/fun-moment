/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import { useEffect, useState, type FormEvent } from 'react';
import { Language } from '../types';
import { ArrowRight, Languages, Loader2, LockKeyhole, ShieldCheck, Sparkles } from 'lucide-react';
import logo from '../../thelogo.png';

type LoginState = 'idle' | 'loading' | 'success' | 'error';

interface AdminLoginScreenProps {
  language: Language;
  setLanguage: (language: Language) => void;
  onAuthenticated: () => void;
  checkingSession: boolean;
}

export default function AdminLoginScreen({
  language,
  setLanguage,
  onAuthenticated,
  checkingSession,
}: AdminLoginScreenProps) {
  const [username, setUsername] = useState('');
  const [password, setPassword] = useState('');
  const [remember, setRemember] = useState(true);
  const [loginState, setLoginState] = useState<LoginState>('idle');
  const [message, setMessage] = useState('');

  useEffect(() => {
    if (!checkingSession) {
      setMessage('');
    }
  }, [checkingSession]);

  const submitLogin = async (event: FormEvent) => {
    event.preventDefault();
    setLoginState('loading');
    setMessage('');

    try {
      const response = await fetch('/login/admin', {
        method: 'POST',
        credentials: 'include',
        headers: {
          'Accept': 'application/json',
          'Content-Type': 'application/json',
          'X-Requested-With': 'XMLHttpRequest',
        },
        body: JSON.stringify({
          username,
          password,
          remember: remember ? '1' : '',
        }),
      });

      const contentType = response.headers.get('content-type') || '';
      const payload = contentType.includes('application/json')
        ? await response.json()
        : { msg: await response.text() };
      if (payload?.status === 'ok') {
        setLoginState('success');
        onAuthenticated();
        return;
      }

      setLoginState('error');
      setMessage(payload?.msg || (language === 'en' ? 'Login failed' : 'فشل تسجيل الدخول'));
    } catch (error) {
      setLoginState('error');
      setMessage(language === 'en' ? 'Could not reach the admin login endpoint' : 'تعذر الوصول إلى صفحة تسجيل الدخول');
      console.error(error);
    }
  };

  return (
    <div className="min-h-screen bg-[radial-gradient(circle_at_top,_#eff6ff_0%,_#f8fafc_40%,_#eef2ff_100%)] text-slate-800">
      <div className="mx-auto flex min-h-screen max-w-7xl items-center px-4 py-8 sm:px-6 lg:px-8">
        <div className="grid w-full overflow-hidden rounded-[2rem] border border-white/60 bg-white/80 shadow-[0_30px_120px_rgba(15,23,42,0.16)] backdrop-blur xl:grid-cols-2">
          <div className="relative hidden min-h-[780px] flex-col justify-between overflow-hidden bg-slate-950 p-10 text-white xl:flex">
            <div className="absolute inset-0 bg-[linear-gradient(135deg,#0f172a_0%,#1e1b4b_55%,#0f766e_100%)] opacity-95" />
            <div className="absolute -left-20 top-12 h-56 w-56 rounded-full bg-cyan-400/20 blur-3xl" />
            <div className="absolute bottom-16 right-8 h-64 w-64 rounded-full bg-indigo-500/20 blur-3xl" />

            <div className="relative z-10 space-y-6">
              <div className="inline-flex items-center gap-3 rounded-2xl border border-white/15 bg-white/10 px-4 py-3 backdrop-blur-sm">
                <img
                  src={logo}
                  alt="FUN MOMENT"
                  className="h-12 w-12 rounded-xl bg-white object-cover p-1 shadow-lg shadow-black/20"
                />
                <div className="leading-tight">
                  <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-cyan-100/80">FUN MOMENT</p>
                  <p className="text-xs text-slate-300">{language === 'en' ? 'Admin Control Panel' : 'لوحة التحكم الإدارية'}</p>
                </div>
              </div>
              <div className="inline-flex items-center gap-2 rounded-full border border-white/15 bg-white/10 px-4 py-2 text-xs font-bold uppercase tracking-[0.25em] text-cyan-100">
                <Sparkles className="h-4 w-4" />
                FUN MOMENT Admin
              </div>
              <div className="space-y-4">
                <h1 className="max-w-xl text-4xl font-black leading-tight tracking-tight">
                  {language === 'en'
                    ? 'A sharper admin gateway for the React dashboard'
                    : 'بوابة إدارة أوضح وأقوى للوحة React'}
                </h1>
                <p className="max-w-xl text-sm leading-7 text-slate-300">
                  {language === 'en'
                    ? 'Use your Laravel admin credentials to enter the dashboard, manage the website, and control the mobile app from one secure panel.'
                    : 'استخدم بيانات دخول مدير Laravel للوصول إلى اللوحة والتحكم في الموقع والتطبيق من مركز واحد آمن.'}
                </p>
              </div>
            </div>

            <div className="relative z-10 grid grid-cols-2 gap-4 text-sm">
              <div className="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                <ShieldCheck className="h-5 w-5 text-cyan-300" />
                <p className="mt-3 font-semibold">{language === 'en' ? 'Session-based auth' : 'تسجيل دخول عبر الجلسة'}</p>
                <p className="mt-1 text-xs leading-6 text-slate-300">{language === 'en' ? 'Laravel remains the source of truth.' : 'Laravel هو المصدر الأساسي للصلاحيات.'}</p>
              </div>
              <div className="rounded-2xl border border-white/10 bg-white/10 p-4 backdrop-blur">
                <LockKeyhole className="h-5 w-5 text-indigo-300" />
                <p className="mt-3 font-semibold">{language === 'en' ? 'Bilingual UI' : 'واجهة ثنائية اللغة'}</p>
                <p className="mt-1 text-xs leading-6 text-slate-300">{language === 'en' ? 'Switch Arabic and English instantly.' : 'التبديل بين العربية والإنجليزية فوراً.'}</p>
              </div>
            </div>
          </div>

          <div className="flex min-h-[780px] items-center justify-center p-6 sm:p-10">
            <div className="w-full max-w-xl">
              <div className="mb-6 flex items-center justify-between">
                <div>
                  <div className="mb-3 inline-flex items-center gap-3 rounded-2xl border border-slate-200 bg-white px-3 py-2 shadow-sm">
                    <img
                      src={logo}
                      alt="FUN MOMENT"
                      className="h-11 w-11 rounded-xl object-cover"
                    />
                    <div className="leading-tight">
                      <p className="text-[10px] font-bold uppercase tracking-[0.28em] text-slate-400">
                        {language === 'en' ? 'FUN MOMENT Dashboard' : 'لوحة فَن مومنت'}
                      </p>
                      <p className="text-xs font-semibold text-slate-700">
                        {language === 'en' ? 'React Admin Login' : 'تسجيل دخول لوحة React'}
                      </p>
                    </div>
                  </div>
                  <p className="text-[10px] font-bold uppercase tracking-[0.3em] text-slate-400">
                    {language === 'en' ? 'Secure access' : 'دخول آمن'}
                  </p>
                  <h2 className="mt-2 text-3xl font-black tracking-tight text-slate-900">
                    {language === 'en' ? 'Sign in to FUN MOMENT' : 'تسجيل الدخول إلى فان مومنت'}
                  </h2>
                </div>

                <button
                  type="button"
                  onClick={() => setLanguage(language === 'en' ? 'ar' : 'en')}
                  className="inline-flex items-center gap-2 rounded-full border border-slate-200 bg-white px-4 py-2 text-xs font-bold text-slate-700 shadow-sm transition hover:border-indigo-200 hover:text-indigo-700"
                >
                  <Languages className="h-4 w-4" />
                  {language === 'en' ? 'العربية' : 'English'}
                </button>
              </div>

              <div className="rounded-[1.75rem] border border-slate-200 bg-white p-6 shadow-[0_24px_80px_rgba(15,23,42,0.1)] sm:p-8">
                <div className="mb-6 rounded-2xl bg-slate-50 p-4">
                  <p className="text-sm font-semibold text-slate-700">
                    {language === 'en'
                      ? 'This login uses the existing Laravel admin session.'
                      : 'تسجيل الدخول هذا يستخدم جلسة إدارة Laravel الحالية.'}
                  </p>
                  <p className="mt-1 text-xs leading-6 text-slate-500">
                    {language === 'en'
                      ? 'Once authenticated, the React dashboard becomes the main admin workspace.'
                      : 'بعد الدخول، تصبح لوحة React هي مساحة الإدارة الرئيسية.'}
                  </p>
                </div>

                {checkingSession && (
                  <div className="mb-5 flex items-center gap-3 rounded-2xl border border-indigo-100 bg-indigo-50 px-4 py-3 text-sm text-indigo-700">
                    <Loader2 className="h-4 w-4 animate-spin" />
                    <span>{language === 'en' ? 'Checking current session...' : 'جاري التحقق من الجلسة الحالية...'}</span>
                  </div>
                )}

                <form onSubmit={submitLogin} className="space-y-4">
                  <div>
                    <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">
                      {language === 'en' ? 'Username or Email' : 'اسم المستخدم أو البريد'}
                    </label>
                    <input
                      type="text"
                      value={username}
                      onChange={(e) => setUsername(e.target.value)}
                      className="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-indigo-400 focus:bg-white"
                      placeholder={language === 'en' ? 'super_admin' : 'super_admin'}
                      autoComplete="username"
                    />
                  </div>

                  <div>
                    <label className="mb-1.5 block text-xs font-bold uppercase tracking-wider text-slate-500">
                      {language === 'en' ? 'Password' : 'كلمة المرور'}
                    </label>
                    <input
                      type="password"
                      value={password}
                      onChange={(e) => setPassword(e.target.value)}
                      className="w-full rounded-2xl border border-slate-200 bg-slate-50 px-4 py-3 text-sm outline-none transition focus:border-indigo-400 focus:bg-white"
                      placeholder="••••••••"
                      autoComplete="current-password"
                    />
                  </div>

                  <div className="flex items-center justify-between gap-3">
                    <label className="flex items-center gap-2 text-xs font-medium text-slate-600">
                      <input
                        type="checkbox"
                        checked={remember}
                        onChange={(e) => setRemember(e.target.checked)}
                        className="h-4 w-4 rounded border-slate-300 text-indigo-600 focus:ring-indigo-500"
                      />
                      {language === 'en' ? 'Remember me' : 'تذكرني'}
                    </label>
                    <span className="text-[10px] uppercase tracking-[0.24em] text-slate-400">
                      {language === 'en' ? 'Secure session' : 'جلسة آمنة'}
                    </span>
                  </div>

                  {message && (
                    <div className={`rounded-2xl border px-4 py-3 text-sm ${loginState === 'error' ? 'border-rose-200 bg-rose-50 text-rose-700' : 'border-emerald-200 bg-emerald-50 text-emerald-700'}`}>
                      {message}
                    </div>
                  )}

                  <button
                    type="submit"
                    disabled={loginState === 'loading'}
                    className="inline-flex w-full items-center justify-center gap-2 rounded-2xl bg-slate-950 px-4 py-3.5 text-sm font-bold text-white transition hover:bg-slate-800 disabled:cursor-not-allowed disabled:opacity-70"
                  >
                    {loginState === 'loading' ? (
                      <>
                        <Loader2 className="h-4 w-4 animate-spin" />
                        <span>{language === 'en' ? 'Signing in...' : 'جاري الدخول...'}</span>
                      </>
                    ) : (
                      <>
                        <span>{language === 'en' ? 'Enter Dashboard' : 'الدخول إلى اللوحة'}</span>
                        <ArrowRight className="h-4 w-4" />
                      </>
                    )}
                  </button>
                </form>
              </div>
            </div>
          </div>
        </div>
      </div>
    </div>
  );
}
