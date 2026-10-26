/**
 * @license
 * SPDX-License-Identifier: Apache-2.0
 */

import React, { useState, useEffect, useCallback, useRef } from 'react';
import {
  Music,
  Disc,
  Layers,
  Upload,
  Play,
  Pause,
  Plus,
  RefreshCw,
  Search,
  CheckCircle,
  AlertCircle,
  Clock,
  Eye,
  Trash2,
  Edit2,
  FileText,
  ShieldCheck,
  Sparkles,
  ArrowUpDown,
  X,
  Volume2,
  Save,
  Image as ImageIcon,
  Loader2
} from 'lucide-react';
import {
  Language,
  UserRole,
  MusicLibraryTrackItem,
  MusicAlbumItem,
  MusicLibraryCounts
} from '../types';
import { translations } from '../translations';
import { api } from '../api';
import { checkPermission } from '../utils/auditLogger';

interface MusicLibraryViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function MusicLibraryView({ language, activeRole }: MusicLibraryViewProps) {
  const t = translations[language];
  const isRtl = language === 'ar';

  const canManage = checkPermission(activeRole, 'manage_services') ||
    checkPermission(activeRole, 'manage_settings') ||
    activeRole === 'super_admin';

  // Navigation tabs: 'tracks' | 'albums' | 'remixes'
  const [activeTab, setActiveTab] = useState<'tracks' | 'albums' | 'remixes'>('tracks');

  // Track state
  const [tracks, setTracks] = useState<MusicLibraryTrackItem[]>([]);
  const [counts, setCounts] = useState<MusicLibraryCounts>({
    all: 0,
    ready: 0,
    processing: 0,
    failed: 0,
    draft: 0,
    published: 0
  });
  const [trackSearch, setTrackSearch] = useState('');
  const [statusFilter, setStatusFilter] = useState('all');
  const [mediaFilter, setMediaFilter] = useState('all');
  const [tracksLoading, setTracksLoading] = useState(false);

  // Album state
  const [albums, setAlbums] = useState<MusicAlbumItem[]>([]);
  const [albumSearch, setAlbumSearch] = useState('');
  const [albumsLoading, setAlbumsLoading] = useState(false);

  // Genres state
  const [genres, setGenres] = useState<Array<{ id: number; name_en: string; name_ar: string; slug: string }>>([]);

  // Modals
  const [showUploadModal, setShowUploadModal] = useState(false);
  const [showEditTrackModal, setShowEditTrackModal] = useState(false);
  const [selectedTrack, setSelectedTrack] = useState<MusicLibraryTrackItem | null>(null);

  const [showCreateAlbumModal, setShowCreateAlbumModal] = useState(false);
  const [showEditAlbumModal, setShowEditAlbumModal] = useState(false);
  const [selectedAlbum, setSelectedAlbum] = useState<MusicAlbumItem | null>(null);

  // Deletion pending states (prevent duplicate clicks/submissions)
  const [deletingTrackId, setDeletingTrackId] = useState<number | null>(null);
  const [deletingAlbumId, setDeletingAlbumId] = useState<number | null>(null);

  // Notification / Alert message
  const [alert, setAlert] = useState<{ type: 'success' | 'error'; message: string } | null>(null);

  // Audio player state
  const [playingTrackId, setPlayingTrackId] = useState<number | null>(null);
  const [audioLoading, setAudioLoading] = useState(false);
  const audioRef = useRef<HTMLAudioElement | null>(null);

  // Load genres
  useEffect(() => {
    api.getMusicGenres().then(res => {
      if (res?.success && Array.isArray(res.genres)) {
        setGenres(res.genres);
      } else if (res && !res.success && res.message) {
        setAlert({ type: 'error', message: res.message });
      }
    }).catch(err => {
      setAlert({ type: 'error', message: err?.message || 'Failed to load music genres' });
    });
  }, []);

  // Fetch Tracks
  const fetchTracks = useCallback(async () => {
    setTracksLoading(true);
    try {
      const res = await api.getMusicLibraryTracks({
        search: trackSearch,
        status: statusFilter,
        media_status: mediaFilter,
      });
      if (res && res.success) {
        const trackList = res.tracks?.data || (Array.isArray((res as any)?.data) ? (res as any).data : []);
        setTracks(trackList);
        if (res.counts) {
          setCounts(res.counts);
        }
      } else {
        const errorMsg = (res as any)?.message_en || (res as any)?.message || 'Failed to load tracks from server';
        setAlert({ type: 'error', message: errorMsg });
      }
    } catch (err: any) {
      setAlert({ type: 'error', message: err?.message || 'Failed to load tracks' });
    } finally {
      setTracksLoading(false);
    }
  }, [trackSearch, statusFilter, mediaFilter]);

  // Fetch Albums
  const fetchAlbums = useCallback(async () => {
    setAlbumsLoading(true);
    try {
      const res = await api.getMusicAlbums({ search: albumSearch });
      if (res && res.success) {
        const albumList = res.albums || (Array.isArray((res as any)?.data) ? (res as any).data : []);
        setAlbums(albumList);
      } else {
        const errorMsg = (res as any)?.message_en || (res as any)?.message || 'Failed to load albums from server';
        setAlert({ type: 'error', message: errorMsg });
      }
    } catch (err: any) {
      setAlert({ type: 'error', message: err?.message || 'Failed to load albums' });
    } finally {
      setAlbumsLoading(false);
    }
  }, [albumSearch]);

  useEffect(() => {
    if (activeTab === 'tracks' || activeTab === 'remixes') {
      fetchTracks();
    }
    if (activeTab === 'albums') {
      fetchAlbums();
    }
  }, [activeTab, fetchTracks, fetchAlbums]);

  // Audio stream playback
  const handleTogglePlay = async (track: MusicLibraryTrackItem) => {
    if (playingTrackId === track.id) {
      if (audioRef.current) {
        audioRef.current.pause();
      }
      setPlayingTrackId(null);
      return;
    }

    setAudioLoading(true);
    try {
      const detail = await api.getMusicLibraryTrack(track.id);
      if (detail?.stream_url) {
        if (!audioRef.current) {
          audioRef.current = new Audio();
          audioRef.current.onended = () => setPlayingTrackId(null);
        }
        audioRef.current.src = detail.stream_url;
        await audioRef.current.play();
        setPlayingTrackId(track.id);
      } else {
        setAlert({ type: 'error', message: isRtl ? 'لا يوجد مسار صوتي متاح للتشغيل' : 'No playable audio stream available' });
      }
    } catch (err: any) {
      setAlert({ type: 'error', message: err.message || 'Playback failed' });
    } finally {
      setAudioLoading(false);
    }
  };

  // Publish / Unpublish track
  const handlePublishTrack = async (id: number) => {
    try {
      const res = await api.publishMusicLibraryTrack(id);
      setAlert({ type: 'success', message: res.message_en });
      fetchTracks();
    } catch (err: any) {
      setAlert({ type: 'error', message: err.message });
    }
  };

  const handleUnpublishTrack = async (id: number) => {
    try {
      const res = await api.unpublishMusicLibraryTrack(id);
      setAlert({ type: 'success', message: res.message_en });
      fetchTracks();
    } catch (err: any) {
      setAlert({ type: 'error', message: err.message });
    }
  };

  const handleDeleteTrack = async (track: MusicLibraryTrackItem) => {
    if (deletingTrackId !== null) return;
    const trackName = isRtl ? (track.title_ar || track.title_en) : track.title_en;
    const confirmPrompt = isRtl
      ? `هل أنت متأكد من حذف أو أرشفة المسار "${trackName}"؟ سيتم تحديث الألبومات المرتبطة به بأمان.`
      : `Are you sure you want to delete or archive the track "${trackName}"? Any associated album counts will be safely updated.`;
    if (!window.confirm(confirmPrompt)) {
      return;
    }
    setDeletingTrackId(track.id);
    try {
      const res = await api.deleteMusicLibraryTrack(track.id);
      // Optimistically remove from state immediately
      setTracks(prev => prev.filter(t => t.id !== track.id));
      const successMsg = isRtl
        ? (res.message_ar || res.message_en || res.message || 'تم حذف المسار بنجاح.')
        : (res.message_en || res.message || 'Track deleted successfully.');
      setAlert({ type: 'success', message: successMsg });
      // Refresh dependent tracks and albums (for track counts)
      fetchTracks();
      fetchAlbums();
    } catch (err: any) {
      setAlert({ type: 'error', message: err.message || 'Failed to delete track' });
    } finally {
      setDeletingTrackId(null);
    }
  };

  // Publish / Delete Album
  const handlePublishAlbum = async (id: number) => {
    try {
      const res = await api.publishMusicAlbum(id);
      setAlert({ type: 'success', message: res.message_en });
      fetchAlbums();
    } catch (err: any) {
      setAlert({ type: 'error', message: err.message });
    }
  };

  const handleDeleteAlbum = async (album: MusicAlbumItem) => {
    if (deletingAlbumId !== null) return;
    const albumTitle = isRtl ? (album.title_ar || album.title_en) : album.title_en;
    const confirmPrompt = isRtl
      ? `هل أنت متأكد من حذف الألبوم "${albumTitle}"؟ سيتم الاحتفاظ بجميع المسارات الصوتية بأمان وفك ارتباطها بالألبوم فقط دون حذفها.`
      : `Are you sure you want to delete the album "${albumTitle}"? All associated tracks and audio files will be safely preserved and unlinked.`;
    if (!window.confirm(confirmPrompt)) {
      return;
    }
    setDeletingAlbumId(album.id);
    try {
      const res = await api.deleteMusicAlbum(album.id);
      // Optimistically remove from state immediately
      setAlbums(prev => prev.filter(a => a.id !== album.id));
      const successMsg = isRtl
        ? (res.message_ar || res.message_en || res.message || 'تم حذف الألبوم الموسيقي بنجاح.')
        : (res.message_en || res.message || 'Album deleted successfully.');
      setAlert({ type: 'success', message: successMsg });
      // Refresh dependent albums and tracks (since album_id was unlinked)
      fetchAlbums();
      fetchTracks();
    } catch (err: any) {
      setAlert({ type: 'error', message: err.message || 'Failed to delete album' });
    } finally {
      setDeletingAlbumId(null);
    }
  };

  return (
    <div className="p-6 max-w-7xl mx-auto space-y-6">
      {/* Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4 border-b border-slate-700/60 pb-6">
        <div>
          <div className="flex items-center gap-3">
            <div className="p-2.5 rounded-xl bg-gradient-to-tr from-amber-500/20 to-orange-500/10 border border-amber-500/30 text-amber-400">
              <Sparkles className="w-6 h-6" />
            </div>
            <div>
              <h1 className="text-2xl font-bold text-white tracking-tight">
                {isRtl ? 'أصول FUN MOMENT — مكتبة الموسيقى الأصلية' : 'FUN MOMENT Originals — Music Library'}
              </h1>
              <p className="text-sm text-slate-400 mt-0.5">
                {isRtl
                  ? 'إدارة الأصول الموسيقية الأصلية التابعة للمنصة، الألبومات والريمكسات الحصرية'
                  : 'Manage platform-owned first-party tracks, official albums, and remix attributions'}
              </p>
            </div>
          </div>
        </div>

        {canManage && (
          <div className="flex items-center gap-2">
            <button
              onClick={() => {
                fetchAlbums();
                setShowUploadModal(true);
              }}
              className="px-4 py-2 bg-gradient-to-r from-amber-500 to-amber-600 hover:from-amber-600 hover:to-amber-700 text-slate-950 font-semibold rounded-lg shadow-lg shadow-amber-500/10 flex items-center gap-2 transition-all cursor-pointer text-sm"
            >
              <Upload className="w-4 h-4" />
              <span>{isRtl ? 'رفع مسار أصلي جديد' : 'Upload Original Track'}</span>
            </button>
            <button
              onClick={() => setShowCreateAlbumModal(true)}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 border border-slate-600 text-white font-medium rounded-lg flex items-center gap-2 transition-all cursor-pointer text-sm"
            >
              <Plus className="w-4 h-4" />
              <span>{isRtl ? 'إنشاء ألبوم' : 'New Album'}</span>
            </button>
          </div>
        )}
      </div>

      {/* Alert toast */}
      {alert && (
        <div
          className={`p-4 rounded-xl border flex items-center justify-between ${
            alert.type === 'success'
              ? 'bg-emerald-500/10 border-emerald-500/30 text-emerald-300'
              : 'bg-red-500/10 border-red-500/30 text-red-300'
          }`}
        >
          <div className="flex items-center gap-3">
            {alert.type === 'success' ? <CheckCircle className="w-5 h-5" /> : <AlertCircle className="w-5 h-5" />}
            <span className="text-sm font-medium">{alert.message}</span>
          </div>
          <button onClick={() => setAlert(null)} className="text-slate-400 hover:text-white">
            <X className="w-4 h-4" />
          </button>
        </div>
      )}

      {/* Stat Cards */}
      <div className="grid grid-cols-2 sm:grid-cols-3 lg:grid-cols-6 gap-3">
        <div className="bg-slate-800/40 border border-slate-700/50 rounded-xl p-3.5">
          <span className="text-xs text-slate-400 block">{isRtl ? 'إجمالي الأصول' : 'Total Originals'}</span>
          <span className="text-xl font-bold text-white mt-1 block">{counts.all}</span>
        </div>
        <div className="bg-slate-800/40 border border-emerald-500/20 rounded-xl p-3.5">
          <span className="text-xs text-emerald-400 block">{isRtl ? 'جاهز للبث' : 'Ready (Transcoded)'}</span>
          <span className="text-xl font-bold text-emerald-400 mt-1 block">{counts.ready}</span>
        </div>
        <div className="bg-slate-800/40 border border-sky-500/20 rounded-xl p-3.5">
          <span className="text-xs text-sky-400 block">{isRtl ? 'قيد المعالجة' : 'Processing'}</span>
          <span className="text-xl font-bold text-sky-400 mt-1 block">{counts.processing}</span>
        </div>
        <div className="bg-slate-800/40 border border-red-500/20 rounded-xl p-3.5">
          <span className="text-xs text-red-400 block">{isRtl ? 'فشل المعالجة' : 'Failed'}</span>
          <span className="text-xl font-bold text-red-400 mt-1 block">{counts.failed}</span>
        </div>
        <div className="bg-slate-800/40 border border-amber-500/20 rounded-xl p-3.5">
          <span className="text-xs text-amber-400 block">{isRtl ? 'منشور في التطبيق' : 'Published in App'}</span>
          <span className="text-xl font-bold text-amber-400 mt-1 block">{counts.published}</span>
        </div>
        <div className="bg-slate-800/40 border border-purple-500/20 rounded-xl p-3.5">
          <span className="text-xs text-purple-400 block">{isRtl ? 'مسودات' : 'Drafts'}</span>
          <span className="text-xl font-bold text-purple-400 mt-1 block">{counts.draft}</span>
        </div>
      </div>

      {/* Navigation Tabs */}
      <div className="flex border-b border-slate-700 gap-6">
        <button
          onClick={() => setActiveTab('tracks')}
          className={`pb-3 font-semibold text-sm flex items-center gap-2 border-b-2 transition-all cursor-pointer ${
            activeTab === 'tracks'
              ? 'border-amber-400 text-amber-400'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          }`}
        >
          <Music className="w-4 h-4" />
          <span>{isRtl ? 'المسارات الأصلية' : 'Original Tracks'}</span>
          <span className="px-1.5 py-0.5 text-xs rounded-full bg-slate-700 text-slate-300 font-mono">
            {tracks.length}
          </span>
        </button>

        <button
          onClick={() => setActiveTab('albums')}
          className={`pb-3 font-semibold text-sm flex items-center gap-2 border-b-2 transition-all cursor-pointer ${
            activeTab === 'albums'
              ? 'border-amber-400 text-amber-400'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          }`}
        >
          <Disc className="w-4 h-4" />
          <span>{isRtl ? 'الألبومات والمجموعات' : 'Albums & Collections'}</span>
          <span className="px-1.5 py-0.5 text-xs rounded-full bg-slate-700 text-slate-300 font-mono">
            {albums.length}
          </span>
        </button>

        <button
          onClick={() => setActiveTab('remixes')}
          className={`pb-3 font-semibold text-sm flex items-center gap-2 border-b-2 transition-all cursor-pointer ${
            activeTab === 'remixes'
              ? 'border-amber-400 text-amber-400'
              : 'border-transparent text-slate-400 hover:text-slate-200'
          }`}
        >
          <Layers className="w-4 h-4" />
          <span>{isRtl ? 'علاقات الريمكس' : 'Remix Attribution'}</span>
        </button>
      </div>

      {/* TAB 1: TRACKS */}
      {activeTab === 'tracks' && (
        <div className="space-y-4">
          {/* Filters & Search */}
          <div className="flex flex-col sm:flex-row gap-3 justify-between items-center bg-slate-800/30 p-3 rounded-xl border border-slate-700/60">
            <div className="relative w-full sm:w-80">
              <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
              <input
                type="text"
                value={trackSearch}
                onChange={(e) => setTrackSearch(e.target.value)}
                placeholder={isRtl ? 'البحث عن مسار أو فنان...' : 'Search tracks or artist...'}
                className="w-full pl-9 pr-4 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
              />
            </div>

            <div className="flex items-center gap-2 w-full sm:w-auto">
              <select
                value={mediaFilter}
                onChange={(e) => setMediaFilter(e.target.value)}
                className="bg-slate-900 border border-slate-700 text-slate-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:border-amber-500"
              >
                <option value="all">{isRtl ? 'جميع حالات الصوت' : 'All Audio States'}</option>
                <option value="ready">{isRtl ? 'جاهز (Ready)' : 'Ready'}</option>
                <option value="processing">{isRtl ? 'قيد المعالجة (Processing)' : 'Processing'}</option>
                <option value="pending">{isRtl ? 'في الانتظار (Pending)' : 'Pending'}</option>
                <option value="failed">{isRtl ? 'فشل (Failed)' : 'Failed'}</option>
              </select>

              <select
                value={statusFilter}
                onChange={(e) => setStatusFilter(e.target.value)}
                className="bg-slate-900 border border-slate-700 text-slate-200 rounded-lg px-3 py-1.5 text-xs focus:outline-none focus:border-amber-500"
              >
                <option value="all">{isRtl ? 'جميع حالات النشر' : 'All Publication'}</option>
                <option value="approved">{isRtl ? 'منشور (Approved)' : 'Approved / Published'}</option>
                <option value="draft">{isRtl ? 'مسودة (Draft)' : 'Draft'}</option>
                <option value="suspended">{isRtl ? 'موقوف (Suspended)' : 'Suspended'}</option>
              </select>

              <button
                onClick={fetchTracks}
                className="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700"
                title="Refresh"
              >
                <RefreshCw className={`w-4 h-4 ${tracksLoading ? 'animate-spin' : ''}`} />
              </button>
            </div>
          </div>

          {/* Tracks Table */}
          <div className="bg-slate-800/40 rounded-xl border border-slate-700/60 overflow-hidden">
            <div className="overflow-x-auto">
              <table className="w-full text-left text-sm text-slate-300">
                <thead className="bg-slate-900/60 text-xs uppercase text-slate-400 border-b border-slate-700/60">
                  <tr>
                    <th className="py-3 px-4">{isRtl ? 'المسار' : 'Track'}</th>
                    <th className="py-3 px-3">{isRtl ? 'الألبوم' : 'Album'}</th>
                    <th className="py-3 px-3">{isRtl ? 'النوع / الإيقاع' : 'Genre / Key'}</th>
                    <th className="py-3 px-3">{isRtl ? 'معالجة الصوت' : 'Audio Pipeline'}</th>
                    <th className="py-3 px-3">{isRtl ? 'حالة النشر' : 'Publication'}</th>
                    <th className="py-3 px-4 text-right">{isRtl ? 'إجراءات' : 'Actions'}</th>
                  </tr>
                </thead>
                <tbody className="divide-y divide-slate-700/40 font-normal">
                  {tracks.length === 0 ? (
                    <tr>
                      <td colSpan={6} className="py-12 text-center text-slate-500">
                        {tracksLoading ? (
                          <div className="flex justify-center items-center gap-2">
                            <RefreshCw className="w-5 h-5 animate-spin text-amber-500" />
                            <span>{isRtl ? 'جاري التحميل...' : 'Loading music tracks...'}</span>
                          </div>
                        ) : (
                          <div>
                            <Music className="w-10 h-10 mx-auto text-slate-600 mb-2" />
                            <p>{isRtl ? 'لا توجد مسارات أصلية مسجلة بعد' : 'No first-party tracks found'}</p>
                          </div>
                        )}
                      </td>
                    </tr>
                  ) : (
                    tracks.map((track) => (
                      <tr key={track.id} className="hover:bg-slate-800/30 transition-colors">
                        <td className="py-3 px-4">
                          <div className="flex items-center gap-3">
                            {track.cover_image_url || track.cover_image ? (
                              <img
                                src={track.cover_image_url || (track.cover_image?.startsWith('http') ? track.cover_image : `/storage/${track.cover_image}`)}
                                alt=""
                                className="w-10 h-10 rounded-lg object-cover border border-slate-700 bg-slate-800 flex-shrink-0"
                              />
                            ) : (
                              <div className="w-10 h-10 rounded-lg bg-slate-800 border border-slate-700 flex items-center justify-center text-slate-500 flex-shrink-0">
                                <Music className="w-5 h-5 text-slate-500" />
                              </div>
                            )}
                            <button
                              onClick={() => handleTogglePlay(track)}
                              disabled={track.media_status !== 'ready' || audioLoading}
                              className={`w-9 h-9 rounded-lg flex items-center justify-center transition-all cursor-pointer ${
                                track.media_status === 'ready'
                                  ? playingTrackId === track.id
                                    ? 'bg-amber-500 text-slate-950 font-bold'
                                    : 'bg-slate-700 hover:bg-amber-500 hover:text-slate-950 text-white'
                                  : 'bg-slate-800 text-slate-600 cursor-not-allowed'
                              }`}
                              title={track.media_status === 'ready' ? 'Preview Audio' : 'Transcoding not ready'}
                            >
                              {playingTrackId === track.id ? (
                                <Pause className="w-4 h-4" />
                              ) : (
                                <Play className="w-4 h-4 ml-0.5" />
                              )}
                            </button>
                            <div>
                              <div className="font-semibold text-white flex items-center gap-2">
                                <span>{track.title_en}</span>
                                <span className="text-xs px-2 py-0.5 rounded-full bg-amber-500/10 text-amber-400 border border-amber-500/20 font-medium">
                                  FUN MOMENT Original
                                </span>
                                {track.original_track_id && (
                                  <span className="text-xs px-1.5 py-0.2 rounded bg-purple-500/10 text-purple-300 border border-purple-500/20 font-mono">
                                    Remix
                                  </span>
                                )}
                              </div>
                              <div className="text-xs text-slate-400 mt-0.5 flex items-center gap-2">
                                <span>{track.artist_name || 'FUN MOMENT'}</span>
                                {track.title_ar && <span>• {track.title_ar}</span>}
                                {track.duration_seconds ? (
                                  <span>• {Math.floor(track.duration_seconds / 60)}:{(track.duration_seconds % 60).toString().padStart(2, '0')}</span>
                                ) : null}
                              </div>
                            </div>
                          </div>
                        </td>

                        <td className="py-3 px-3">
                          {track.album_title ? (
                            <div className="text-xs text-slate-300">
                              <span className="font-medium text-amber-200 block">{track.album_title}</span>
                              {track.track_number && (
                                <span className="text-slate-500 font-mono text-[11px]">Track #{track.track_number}</span>
                              )}
                            </div>
                          ) : (
                            <span className="text-xs text-slate-500 italic">— Single —</span>
                          )}
                        </td>

                        <td className="py-3 px-3">
                          <div className="text-xs">
                            <span className="text-slate-300 block">{track.genre_name || '—'}</span>
                            <span className="text-slate-500 font-mono text-[11px]">
                              {track.bpm ? `${track.bpm} BPM` : ''} {track.track_key ? `• ${track.track_key}` : ''}
                            </span>
                          </div>
                        </td>

                        <td className="py-3 px-3">
                          <span
                            className={`inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full text-xs font-medium ${
                              track.media_status === 'ready'
                                ? 'bg-emerald-500/10 text-emerald-400 border border-emerald-500/20'
                                : track.media_status === 'processing'
                                ? 'bg-sky-500/10 text-sky-400 border border-sky-500/20 animate-pulse'
                                : track.media_status === 'failed'
                                ? 'bg-red-500/10 text-red-400 border border-red-500/20'
                                : 'bg-slate-700/50 text-slate-400 border border-slate-600/40'
                            }`}
                          >
                            {track.media_status === 'ready' && <CheckCircle className="w-3 h-3" />}
                            {track.media_status === 'processing' && <RefreshCw className="w-3 h-3 animate-spin" />}
                            {track.media_status === 'failed' && <AlertCircle className="w-3 h-3" />}
                            {track.media_status === 'pending' && <Clock className="w-3 h-3" />}
                            <span className="capitalize">{track.media_status}</span>
                          </span>
                          {track.processing_error && (
                            <div className="text-[10px] text-red-400 mt-1 max-w-[150px] truncate" title={track.processing_error}>
                              {track.processing_error}
                            </div>
                          )}
                        </td>

                        <td className="py-3 px-3">
                          <span
                            className={`inline-flex items-center px-2 py-0.5 rounded text-xs font-medium ${
                              track.status === 'approved'
                                ? 'bg-emerald-500/20 text-emerald-300'
                                : track.status === 'draft'
                                ? 'bg-amber-500/20 text-amber-300'
                                : 'bg-red-500/20 text-red-300'
                            }`}
                          >
                            {track.status === 'approved' ? (isRtl ? 'منشور (Approved)' : 'Published') : track.status}
                          </span>
                        </td>

                        <td className="py-3 px-4 text-right">
                          <div className="flex items-center justify-end gap-1.5">
                            {canManage && (
                              <>
                                {track.status !== 'approved' ? (
                                  <button
                                    onClick={() => handlePublishTrack(track.id)}
                                    disabled={track.media_status !== 'ready'}
                                    className={`px-2.5 py-1 rounded text-xs font-semibold transition-all ${
                                      track.media_status === 'ready'
                                        ? 'bg-emerald-600 hover:bg-emerald-500 text-white cursor-pointer'
                                        : 'bg-slate-700 text-slate-500 cursor-not-allowed'
                                    }`}
                                    title={track.media_status === 'ready' ? 'Publish to catalog' : 'Audio must be ready before publishing'}
                                  >
                                    {isRtl ? 'نشر' : 'Publish'}
                                  </button>
                                ) : (
                                  <button
                                    onClick={() => handleUnpublishTrack(track.id)}
                                    className="px-2.5 py-1 bg-amber-600/30 hover:bg-amber-600/50 text-amber-200 border border-amber-500/40 rounded text-xs font-semibold cursor-pointer transition-all"
                                  >
                                    {isRtl ? 'إلغاء النشر' : 'Unpublish'}
                                  </button>
                                )}

                                <button
                                  onClick={() => {
                                    setSelectedTrack(track);
                                    setShowEditTrackModal(true);
                                  }}
                                  className="p-1.5 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded transition-all cursor-pointer"
                                  title="Edit Metadata"
                                >
                                  <Edit2 className="w-3.5 h-3.5" />
                                </button>

                                <button
                                  onClick={() => handleDeleteTrack(track)}
                                  disabled={deletingTrackId === track.id || deletingTrackId !== null}
                                  className="p-1.5 bg-red-950/40 hover:bg-red-900/60 disabled:opacity-50 disabled:cursor-not-allowed text-red-300 border border-red-500/20 rounded transition-all cursor-pointer"
                                  title={deletingTrackId === track.id ? (isRtl ? 'جاري الحذف...' : 'Deleting...') : (isRtl ? 'حذف أو أرشفة المسار' : 'Delete or Archive Track')}
                                >
                                  {deletingTrackId === track.id ? (
                                    <Loader2 className="w-3.5 h-3.5 animate-spin text-red-400" />
                                  ) : (
                                    <Trash2 className="w-3.5 h-3.5" />
                                  )}
                                </button>
                              </>
                            )}
                          </div>
                        </td>
                      </tr>
                    ))
                  )}
                </tbody>
              </table>
            </div>
          </div>
        </div>
      )}

      {/* TAB 2: ALBUMS */}
      {activeTab === 'albums' && (
        <div className="space-y-4">
          <div className="flex justify-between items-center bg-slate-800/30 p-3 rounded-xl border border-slate-700/60">
            <div className="relative w-full sm:w-80">
              <Search className="w-4 h-4 absolute left-3 top-3 text-slate-400" />
              <input
                type="text"
                value={albumSearch}
                onChange={(e) => setAlbumSearch(e.target.value)}
                placeholder={isRtl ? 'البحث عن ألبوم...' : 'Search albums...'}
                className="w-full pl-9 pr-4 py-2 bg-slate-900/60 border border-slate-700 rounded-lg text-sm text-white placeholder-slate-500 focus:outline-none focus:border-amber-500"
              />
            </div>
            <button
              onClick={fetchAlbums}
              className="p-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg border border-slate-700"
            >
              <RefreshCw className={`w-4 h-4 ${albumsLoading ? 'animate-spin' : ''}`} />
            </button>
          </div>

          <div className="grid grid-cols-1 md:grid-cols-2 lg:grid-cols-3 gap-4">
            {albums.length === 0 ? (
              <div className="col-span-full py-12 text-center text-slate-500 bg-slate-800/30 rounded-xl border border-slate-700/50">
                <Disc className="w-12 h-12 mx-auto text-slate-600 mb-2" />
                <p>{isRtl ? 'لا توجد ألبومات مضافة حالياً' : 'No albums created yet'}</p>
                {canManage && (
                  <button
                    onClick={() => setShowCreateAlbumModal(true)}
                    className="mt-3 px-4 py-1.5 bg-amber-500 text-slate-950 font-semibold rounded-lg text-xs"
                  >
                    {isRtl ? 'إنشاء أول ألبوم' : 'Create First Album'}
                  </button>
                )}
              </div>
            ) : (
              albums.map((album) => (
                <div key={album.id} className="bg-slate-800/40 rounded-xl border border-slate-700/60 p-4 space-y-3">
                  <div className="flex items-start gap-3">
                    {album.cover_image_url || album.cover_image ? (
                      <img
                        src={album.cover_image_url || (album.cover_image?.startsWith('http') ? album.cover_image : `/storage/${album.cover_image}`)}
                        alt={album.title_en}
                        className="w-12 h-12 rounded-lg object-cover border border-slate-700 bg-slate-900 flex-shrink-0"
                      />
                    ) : (
                      <div className="w-12 h-12 rounded-lg border border-slate-700 bg-slate-900 flex items-center justify-center flex-shrink-0 text-slate-500">
                        <Disc className="w-6 h-6 text-amber-500/60" />
                      </div>
                    )}
                    <div className="flex-1 min-w-0">
                      <div className="flex justify-between items-start gap-2">
                        <div className="min-w-0">
                          <h3 className="font-bold text-white text-base truncate">{album.title_en}</h3>
                          <p className="text-xs text-slate-400 truncate">{album.title_ar}</p>
                        </div>
                        <span
                          className={`text-xs px-2 py-0.5 rounded font-semibold flex-shrink-0 ${
                            album.status === 'published'
                              ? 'bg-emerald-500/20 text-emerald-300'
                              : 'bg-amber-500/20 text-amber-300'
                          }`}
                        >
                          {album.status}
                        </span>
                      </div>
                    </div>
                  </div>

                  <p className="text-xs text-slate-300 line-clamp-2">
                    {album.description_en || album.description_ar || 'Platform official album collection.'}
                  </p>

                  <div className="text-xs text-slate-400 flex items-center justify-between border-t border-slate-700/40 pt-2 font-mono">
                    <span>Artist: {album.artist_name || 'FUN MOMENT'}</span>
                    <span>{album.tracks_count || (album.tracks ? album.tracks.length : 0)} Tracks</span>
                  </div>

                  {canManage && (
                    <div className="flex items-center justify-end gap-2 border-t border-slate-700/40 pt-2">
                      {album.status !== 'published' ? (
                        <button
                          onClick={() => handlePublishAlbum(album.id)}
                          className="px-2.5 py-1 bg-emerald-600 hover:bg-emerald-500 text-white rounded text-xs font-semibold cursor-pointer"
                        >
                          {isRtl ? 'نشر' : 'Publish'}
                        </button>
                      ) : null}
                      <button
                        onClick={() => {
                          setSelectedAlbum(album);
                          setShowEditAlbumModal(true);
                        }}
                        className="px-2.5 py-1 bg-slate-700 hover:bg-slate-600 text-slate-200 rounded text-xs font-medium cursor-pointer"
                      >
                        {isRtl ? 'تعديل' : 'Edit'}
                      </button>
                      <button
                        onClick={() => handleDeleteAlbum(album)}
                        disabled={deletingAlbumId === album.id || deletingAlbumId !== null}
                        className="px-2 py-1 bg-red-950/40 hover:bg-red-900/60 disabled:opacity-50 disabled:cursor-not-allowed text-red-300 border border-red-500/20 rounded text-xs cursor-pointer flex items-center justify-center min-w-[28px]"
                        title={deletingAlbumId === album.id ? (isRtl ? 'جاري الحذف...' : 'Deleting...') : (isRtl ? 'حذف الألبوم' : 'Delete Album')}
                      >
                        {deletingAlbumId === album.id ? (
                          <Loader2 className="w-3.5 h-3.5 animate-spin text-red-400" />
                        ) : (
                          <Trash2 className="w-3.5 h-3.5" />
                        )}
                      </button>
                    </div>
                  )}
                </div>
              ))
            )}
          </div>
        </div>
      )}

      {/* TAB 3: REMIXES */}
      {activeTab === 'remixes' && (
        <div className="bg-slate-800/40 rounded-xl border border-slate-700/60 p-6 space-y-4">
          <div className="border-b border-slate-700/60 pb-3">
            <h3 className="font-bold text-white text-base">
              {isRtl ? 'شجرة ونسب الريمكسات (Remix Attribution Graph)' : 'Remix Attribution Tree'}
            </h3>
            <p className="text-xs text-slate-400 mt-1">
              {isRtl
                ? 'الربط المباشر بين الإصدارات المعدلة (Remixes) والمسارات الأصلية (Original Master Tracks)'
                : 'Direct linkage between altered renditions and original first-party master sources'}
            </p>
          </div>

          <div className="space-y-3">
            {tracks.filter(t => t.original_track_id || (t.remixes_count && t.remixes_count > 0)).length === 0 ? (
              <p className="text-sm text-slate-500 py-6 text-center">
                {isRtl ? 'لا توجد علاقات ريمكس مرتبطة حالياً' : 'No remix relationships registered yet'}
              </p>
            ) : (
              tracks
                .filter(t => t.original_track_id)
                .map((remix) => (
                  <div key={remix.id} className="p-4 rounded-xl bg-slate-900/60 border border-slate-700 flex flex-col sm:flex-row justify-between items-start sm:items-center gap-3">
                    <div>
                      <div className="flex items-center gap-2">
                        <span className="font-semibold text-purple-300">{remix.title_en}</span>
                        <span className="text-xs px-2 py-0.5 rounded bg-purple-500/10 text-purple-300 border border-purple-500/20">
                          Remix
                        </span>
                      </div>
                      <div className="text-xs text-slate-400 mt-1">
                        Remix Artist: <span className="text-slate-200">{remix.artist_name}</span>
                      </div>
                    </div>

                    <div className="flex items-center gap-2 bg-slate-800/80 px-3 py-2 rounded-lg border border-slate-700/80 text-xs">
                      <span className="text-slate-400">Source Track:</span>
                      <span className="font-semibold text-amber-300">
                        {remix.original_track ? remix.original_track.title_en : `Track #${remix.original_track_id}`}
                      </span>
                    </div>
                  </div>
                ))
            )}
          </div>
        </div>
      )}

      {/* UPLOAD TRACK MODAL */}
      {showUploadModal && (
        <UploadTrackModal
          genres={genres}
          albums={albums}
          tracks={tracks}
          language={language}
          onClose={() => setShowUploadModal(false)}
          onSuccess={(msg) => {
            setShowUploadModal(false);
            setAlert({ type: 'success', message: msg });
            fetchTracks();
          }}
        />
      )}

      {/* EDIT TRACK MODAL */}
      {showEditTrackModal && selectedTrack && (
        <EditTrackModal
          track={selectedTrack}
          genres={genres}
          albums={albums}
          tracks={tracks}
          language={language}
          onClose={() => {
            setShowEditTrackModal(false);
            setSelectedTrack(null);
          }}
          onSuccess={(msg) => {
            setShowEditTrackModal(false);
            setSelectedTrack(null);
            setAlert({ type: 'success', message: msg });
            fetchTracks();
          }}
        />
      )}

      {/* CREATE ALBUM MODAL */}
      {showCreateAlbumModal && (
        <CreateAlbumModal
          genres={genres}
          language={language}
          onClose={() => setShowCreateAlbumModal(false)}
          onSuccess={(msg) => {
            setShowCreateAlbumModal(false);
            setAlert({ type: 'success', message: msg });
            fetchAlbums();
          }}
        />
      )}

      {/* EDIT ALBUM MODAL */}
      {showEditAlbumModal && selectedAlbum && (
        <EditAlbumModal
          album={selectedAlbum}
          genres={genres}
          language={language}
          onClose={() => {
            setShowEditAlbumModal(false);
            setSelectedAlbum(null);
          }}
          onSuccess={(msg) => {
            setShowEditAlbumModal(false);
            setSelectedAlbum(null);
            setAlert({ type: 'success', message: msg });
            fetchAlbums();
          }}
        />
      )}
    </div>
  );
}

// -------------------------------------------------------------------------------------
// Upload Track Modal Component
// -------------------------------------------------------------------------------------
function UploadTrackModal({
  genres,
  albums,
  tracks,
  language,
  onClose,
  onSuccess
}: {
  genres: any[];
  albums: any[];
  tracks: any[];
  language: Language;
  onClose: () => void;
  onSuccess: (msg: string) => void;
}) {
  const isRtl = language === 'ar';
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [titleEn, setTitleEn] = useState('');
  const [titleAr, setTitleAr] = useState('');
  const [artistName, setArtistName] = useState('FUN MOMENT Originals');
  const [genreId, setGenreId] = useState<string>(genres[0]?.id ? String(genres[0].id) : '');
  const [albumId, setAlbumId] = useState<string>('');
  const [trackNumber, setTrackNumber] = useState<string>('');
  const [originalTrackId, setOriginalTrackId] = useState<string>('');
  const [bpm, setBpm] = useState<string>('');
  const [trackKey, setTrackKey] = useState<string>('');
  const [descriptionEn, setDescriptionEn] = useState('');
  const [descriptionAr, setDescriptionAr] = useState('');
  const [rightsType, setRightsType] = useState('original_composition');
  const [rightsAccepted, setRightsAccepted] = useState(true);
  const [rightsNotes, setRightsNotes] = useState('FUN MOMENT First-party official library production.');

  const [audioFile, setAudioFile] = useState<File | null>(null);
  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [coverPreview, setCoverPreview] = useState<string | null>(null);

  useEffect(() => {
    return () => {
      if (coverPreview) {
        URL.revokeObjectURL(coverPreview);
      }
    };
  }, [coverPreview]);

  const handleCoverChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
      setError(
        isRtl
          ? 'صيغة الغلاف غير مدعومة. يرجى اختيار ملف صورة بتنسيق JPG أو PNG أو WebP'
          : 'Unsupported cover format. Please select a JPG, PNG, or WebP image.'
      );
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setError(
        isRtl
          ? 'حجم صورة الغلاف يتجاوز الحد الأقصى (٥ ميجابايت)'
          : 'Cover image exceeds the maximum allowed size (5MB).'
      );
      return;
    }

    setError(null);
    setCoverFile(file);
    if (coverPreview) {
      URL.revokeObjectURL(coverPreview);
    }
    setCoverPreview(URL.createObjectURL(file));
  };

  const handleRemoveCover = () => {
    setCoverFile(null);
    if (coverPreview) {
      URL.revokeObjectURL(coverPreview);
      setCoverPreview(null);
    }
  };

  const handleOriginalTrackChange = (newTrackId: string) => {
    setOriginalTrackId(newTrackId);
    if (newTrackId) {
      setRightsType('licensed_remix');
    } else if (rightsType === 'licensed_remix') {
      setRightsType('original_composition');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    if (!audioFile) {
      setError(isRtl ? 'يرجى اختيار ملف الصوت (WAV أو MP3)' : 'Please select an audio file (WAV or MP3)');
      return;
    }
    if (!rightsAccepted) {
      setError(isRtl ? 'يجب الإقرار بصلاحية وحقوق نشر العمل' : 'You must confirm the rights declaration');
      return;
    }

    setSubmitting(true);
    setError(null);

    const formData = new FormData();
    formData.append('title_en', titleEn);
    if (titleAr) formData.append('title_ar', titleAr);
    formData.append('artist_name', artistName);
    if (genreId) formData.append('genre_id', genreId);
    if (albumId) formData.append('album_id', albumId);
    if (trackNumber) formData.append('track_number', trackNumber);
    if (originalTrackId) formData.append('original_track_id', originalTrackId);
    if (bpm) formData.append('bpm', bpm);
    if (trackKey) formData.append('track_key', trackKey);
    if (descriptionEn) formData.append('description_en', descriptionEn);
    if (descriptionAr) formData.append('description_ar', descriptionAr);
    formData.append('rights_declaration_type', rightsType);
    formData.append('rights_agreement_accepted', '1');
    if (rightsNotes) formData.append('rights_notes', rightsNotes);

    formData.append('audio_file', audioFile);
    if (coverFile) formData.append('cover_image', coverFile);

    try {
      const res = await api.uploadMusicLibraryTrack(formData);
      onSuccess(res.message_en);
    } catch (err: any) {
      setError(err.message || 'Upload failed');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm overflow-y-auto">
      <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-2xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div className="sticky top-0 bg-slate-900/95 backdrop-blur px-6 py-4 border-b border-slate-800 flex justify-between items-center z-10">
          <div className="flex items-center gap-2">
            <Upload className="w-5 h-5 text-amber-400" />
            <h2 className="text-lg font-bold text-white">
              {isRtl ? 'رفع مسار أصلي جديد (FUN MOMENT Original)' : 'Upload New First-Party Track'}
            </h2>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-white">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-xs">
              {error}
            </div>
          )}

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Title (English) *
              </label>
              <input
                type="text"
                required
                value={titleEn}
                onChange={(e) => setTitleEn(e.target.value)}
                placeholder="e.g. Midnight Mirage (Original Mix)"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Title (Arabic)
              </label>
              <input
                type="text"
                value={titleAr}
                onChange={(e) => setTitleAr(e.target.value)}
                placeholder="مثال: سراب منتصف الليل"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500 text-right"
              />
            </div>
          </div>

          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Artist Name *
              </label>
              <input
                type="text"
                required
                value={artistName}
                onChange={(e) => setArtistName(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Genre
              </label>
              <select
                value={genreId}
                onChange={(e) => setGenreId(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              >
                <option value="">Select Genre</option>
                {genres.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name_en} / {g.name_ar}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Cover Artwork Upload Card */}
          <div className="p-4 rounded-xl bg-slate-800/60 border border-slate-700 space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <ImageIcon className="w-4 h-4 text-amber-400" />
                <label className="text-xs font-semibold text-white">
                  {isRtl ? 'صورة الغلاف (اختياري)' : 'Cover Artwork (Optional)'}
                </label>
              </div>
              <span className="text-[11px] text-slate-400">JPG, PNG, WebP (Max 5MB)</span>
            </div>

            <div className="flex items-center gap-4">
              {coverPreview ? (
                <div className="relative group w-20 h-20 rounded-xl overflow-hidden border border-amber-500/40 bg-slate-950 flex-shrink-0 shadow-md">
                  <img src={coverPreview} alt="Cover Preview" className="w-full h-full object-cover" />
                  <button
                    type="button"
                    onClick={handleRemoveCover}
                    className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-rose-400 hover:text-rose-300"
                    title={isRtl ? 'إزالة الغلاف' : 'Remove Cover'}
                  >
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              ) : (
                <div className="w-20 h-20 rounded-xl border border-dashed border-slate-600 bg-slate-900/60 flex flex-col items-center justify-center text-slate-500 flex-shrink-0">
                  <ImageIcon className="w-6 h-6 mb-1 text-slate-600" />
                  <span className="text-[10px]">{isRtl ? 'لا يوجد غلاف' : 'No artwork'}</span>
                </div>
              )}

              <div className="flex-1 space-y-2">
                <div className="flex items-center gap-2">
                  <input
                    type="file"
                    id="track-cover-upload"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    className="hidden"
                    onChange={handleCoverChange}
                  />
                  <label
                    htmlFor="track-cover-upload"
                    className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-600 bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 transition-colors"
                  >
                    <Upload className="w-3.5 h-3.5 text-amber-400" />
                    <span>
                      {coverPreview
                        ? (isRtl ? 'استبدال الغلاف' : 'Replace Artwork')
                        : (isRtl ? 'اختيار صورة الغلاف' : 'Upload Cover Artwork')}
                    </span>
                  </label>
                  {coverPreview && (
                    <button
                      type="button"
                      onClick={handleRemoveCover}
                      className="px-2.5 py-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 text-xs transition-colors"
                    >
                      {isRtl ? 'إزالة' : 'Remove'}
                    </button>
                  )}
                </div>
                <p className="text-[11px] text-slate-400">
                  {albumId
                    ? (isRtl
                        ? 'إذا تُرك فارغاً، سيرث المسار صورة غلاف الألبوم المختار تلقائياً.'
                        : 'If left blank, track will inherit the selected album artwork.')
                    : (isRtl
                        ? 'يُعرض في تطبيق المستمعين والمشغل الموسيقي وقوائم التشغيل.'
                        : 'Displayed in mobile catalog, player drawer, and public playlists.')}
                </p>
              </div>
            </div>
          </div>

          {/* Audio Upload File */}
          <div className="p-4 rounded-xl bg-slate-800/60 border border-dashed border-slate-600 space-y-2">
            <label className="block text-xs font-semibold text-amber-300">
              Master Audio File (MP3, WAV, AAC, FLAC, OGG) *
            </label>
            <input
              type="file"
              required
              accept="audio/*,.wav,.mp3,.aac,.flac,.ogg"
              onChange={(e) => {
                if (e.target.files && e.target.files[0]) {
                  setAudioFile(e.target.files[0]);
                }
              }}
              className="text-xs text-slate-300 file:mr-4 file:py-2 file:px-4 file:rounded-lg file:border-0 file:text-xs file:font-semibold file:bg-amber-500 file:text-slate-950 hover:file:bg-amber-600 cursor-pointer"
            />
            <p className="text-[11px] text-slate-400">
              The audio file will be stored securely on the private storage volume and automatically transcoded to HLS/AAC rendition + preview.
            </p>
          </div>

          {/* Album & Track Number */}
          <div className="grid grid-cols-1 sm:grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Assign to Album (Optional)
              </label>
              <select
                value={albumId}
                onChange={(e) => setAlbumId(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              >
                <option value="">— Standalone Single —</option>
                {albums.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.title_en}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">
                Track Number
              </label>
              <input
                type="number"
                min="1"
                value={trackNumber}
                onChange={(e) => setTrackNumber(e.target.value)}
                placeholder="e.g. 1"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>

          {/* Remix Link */}
          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">
              Is this track a Remix? (Select Original Source Track)
            </label>
            <select
              value={originalTrackId}
              onChange={(e) => handleOriginalTrackChange(e.target.value)}
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            >
              <option value="">— Not a remix (Original Master) —</option>
              {tracks.map((t) => (
                <option key={t.id} value={t.id}>
                  #{t.id} {t.title_en} ({t.artist_name})
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">BPM</label>
              <input
                type="number"
                value={bpm}
                onChange={(e) => setBpm(e.target.value)}
                placeholder="128"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Musical Key</label>
              <input
                type="text"
                value={trackKey}
                onChange={(e) => setTrackKey(e.target.value)}
                placeholder="Am"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>

          {/* Rights Declaration */}
          <div className="p-3 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-2">
            <div className="flex items-center gap-2">
              <ShieldCheck className="w-4 h-4 text-emerald-400" />
              <span className="text-xs font-semibold text-white">
                {isRtl ? 'إقرار حقوق الملكية للمنصة' : 'Platform Rights Declaration'}
              </span>
            </div>
            <select
              value={rightsType}
              onChange={(e) => setRightsType(e.target.value)}
              className="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded text-xs text-slate-200"
            >
              <option value="original_composition">
                {isRtl ? 'عمل موسيقي أصلي (مملوك لـ FUN MOMENT بالكامل)' : 'Original Composition (FUN MOMENT Platform-Owned)'}
              </option>
              <option value="licensed_remix">
                {isRtl ? 'ريمكس مرخّص (منسوب لمسار رئيسي)' : 'Licensed Remix (Attributed to Master Track)'}
              </option>
              <option value="public_domain">
                {isRtl ? 'ملكية عامة / مصرح حقوقياً' : 'Public Domain / Rights-Cleared Composition'}
              </option>
            </select>
            <label className="flex items-center gap-2 text-xs text-slate-300 cursor-pointer pt-1">
              <input
                type="checkbox"
                checked={rightsAccepted}
                onChange={(e) => setRightsAccepted(e.target.checked)}
                className="rounded text-amber-500 focus:ring-0"
              />
              <span>
                {isRtl
                  ? 'أقر بأن FUN MOMENT تمتلك جميع حقوق النشر والتوزيع والبث لهذا المسار الموسيقي.'
                  : 'I certify that FUN MOMENT holds all required publishing and streaming rights for this asset.'}
              </span>
            </label>
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-medium"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-lg text-sm flex items-center gap-2 shadow-lg shadow-amber-500/10 cursor-pointer"
            >
              {submitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Upload className="w-4 h-4" />}
              <span>{submitting ? 'Uploading & Processing...' : 'Upload Master'}</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// -------------------------------------------------------------------------------------
// Edit Track Modal Component
// -------------------------------------------------------------------------------------
function EditTrackModal({
  track,
  genres,
  albums,
  tracks,
  language,
  onClose,
  onSuccess
}: {
  track: MusicLibraryTrackItem;
  genres: any[];
  albums: any[];
  tracks: any[];
  language: Language;
  onClose: () => void;
  onSuccess: (msg: string) => void;
}) {
  const isRtl = language === 'ar';
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [titleEn, setTitleEn] = useState(track.title_en || '');
  const [titleAr, setTitleAr] = useState(track.title_ar || '');
  const [artistName, setArtistName] = useState(track.artist_name || 'FUN MOMENT');
  const [genreId, setGenreId] = useState<string>(track.genre_id ? String(track.genre_id) : '');
  const [albumId, setAlbumId] = useState<string>(track.album_id ? String(track.album_id) : '');
  const [trackNumber, setTrackNumber] = useState<string>(track.track_number ? String(track.track_number) : '');
  const [originalTrackId, setOriginalTrackId] = useState<string>(track.original_track_id ? String(track.original_track_id) : '');
  const [bpm, setBpm] = useState<string>(track.bpm ? String(track.bpm) : '');
  const [trackKey, setTrackKey] = useState<string>(track.track_key || '');
  const [descriptionEn, setDescriptionEn] = useState(track.description_en || '');
  const [descriptionAr, setDescriptionAr] = useState(track.description_ar || '');
  const [rightsType, setRightsType] = useState<string>(
    track.rights_declaration_type && ['original_composition', 'licensed_remix', 'public_domain'].includes(track.rights_declaration_type)
      ? track.rights_declaration_type
      : (track.original_track_id ? 'licensed_remix' : 'original_composition')
  );

  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [coverPreview, setCoverPreview] = useState<string | null>(
    track.cover_image_url || (track.cover_image ? (track.cover_image.startsWith('http') ? track.cover_image : `/storage/${track.cover_image}`) : null)
  );

  useEffect(() => {
    return () => {
      if (coverPreview && coverPreview.startsWith('blob:')) {
        URL.revokeObjectURL(coverPreview);
      }
    };
  }, [coverPreview]);

  const handleCoverChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
      setError(
        isRtl
          ? 'صيغة الغلاف غير مدعومة. يرجى اختيار ملف صورة بتنسيق JPG أو PNG أو WebP'
          : 'Unsupported cover format. Please select a JPG, PNG, or WebP image.'
      );
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setError(
        isRtl
          ? 'حجم صورة الغلاف يتجاوز الحد الأقصى (٥ ميجابايت)'
          : 'Cover image exceeds the maximum allowed size (5MB).'
      );
      return;
    }

    setError(null);
    setCoverFile(file);
    if (coverPreview && coverPreview.startsWith('blob:')) {
      URL.revokeObjectURL(coverPreview);
    }
    setCoverPreview(URL.createObjectURL(file));
  };

  const handleRemoveCover = () => {
    setCoverFile(null);
    if (coverPreview && coverPreview.startsWith('blob:')) {
      URL.revokeObjectURL(coverPreview);
    }
    setCoverPreview(track.cover_image_url || (track.cover_image ? (track.cover_image.startsWith('http') ? track.cover_image : `/storage/${track.cover_image}`) : null));
  };

  const handleOriginalTrackChange = (newTrackId: string) => {
    setOriginalTrackId(newTrackId);
    if (newTrackId) {
      setRightsType('licensed_remix');
    } else if (rightsType === 'licensed_remix') {
      setRightsType('original_composition');
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    const formData = new FormData();
    formData.append('title_en', titleEn);
    if (titleAr) formData.append('title_ar', titleAr);
    if (artistName) formData.append('artist_name', artistName);
    if (genreId) formData.append('genre_id', genreId);
    if (albumId) formData.append('album_id', albumId);
    if (trackNumber) formData.append('track_number', trackNumber);
    if (originalTrackId) formData.append('original_track_id', originalTrackId);
    if (bpm) formData.append('bpm', bpm);
    if (trackKey) formData.append('track_key', trackKey);
    if (descriptionEn) formData.append('description_en', descriptionEn);
    if (descriptionAr) formData.append('description_ar', descriptionAr);
    formData.append('rights_declaration_type', rightsType);
    if (coverFile) {
      formData.append('cover_image', coverFile);
    }

    try {
      const res = await api.updateMusicLibraryTrack(track.id, formData);
      onSuccess(res.message_en);
    } catch (err: any) {
      setError(err.message || 'Update failed');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm overflow-y-auto">
      <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-xl max-h-[90vh] overflow-y-auto shadow-2xl">
        <div className="sticky top-0 bg-slate-900/95 backdrop-blur px-6 py-4 border-b border-slate-800 flex justify-between items-center z-10">
          <div className="flex items-center gap-2">
            <Edit2 className="w-5 h-5 text-amber-400" />
            <h2 className="text-lg font-bold text-white">Edit Track #{track.id}</h2>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-white">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-xs">
              {error}
            </div>
          )}

          {/* Cover Artwork Card */}
          <div className="p-3.5 rounded-xl bg-slate-800/60 border border-slate-700 space-y-2.5">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <ImageIcon className="w-4 h-4 text-amber-400" />
                <label className="text-xs font-semibold text-white">
                  {isRtl ? 'صورة الغلاف' : 'Cover Artwork'}
                </label>
              </div>
              <span className="text-[11px] text-slate-400">JPG, PNG, WebP (Max 5MB)</span>
            </div>

            <div className="flex items-center gap-3.5">
              {coverPreview ? (
                <div className="relative group w-16 h-16 rounded-xl overflow-hidden border border-amber-500/40 bg-slate-950 flex-shrink-0 shadow-md">
                  <img src={coverPreview} alt="Cover Preview" className="w-full h-full object-cover" />
                  {coverFile && (
                    <button
                      type="button"
                      onClick={handleRemoveCover}
                      className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-rose-400 hover:text-rose-300"
                      title={isRtl ? 'إلغاء الصورة الجديدة' : 'Revert Image'}
                    >
                      <Trash2 className="w-4 h-4" />
                    </button>
                  )}
                </div>
              ) : (
                <div className="w-16 h-16 rounded-xl border border-dashed border-slate-600 bg-slate-900/60 flex flex-col items-center justify-center text-slate-500 flex-shrink-0">
                  <ImageIcon className="w-5 h-5 mb-1 text-slate-600" />
                  <span className="text-[9px]">{isRtl ? 'لا يوجد غلاف' : 'No artwork'}</span>
                </div>
              )}

              <div className="flex-1 space-y-1.5">
                <div className="flex items-center gap-2">
                  <input
                    type="file"
                    id="track-edit-cover-upload"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    className="hidden"
                    onChange={handleCoverChange}
                  />
                  <label
                    htmlFor="track-edit-cover-upload"
                    className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-600 bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 transition-colors"
                  >
                    <Upload className="w-3.5 h-3.5 text-amber-400" />
                    <span>
                      {coverPreview
                        ? (isRtl ? 'استبدال الغلاف' : 'Replace Artwork')
                        : (isRtl ? 'اختيار صورة الغلاف' : 'Upload Cover Artwork')}
                    </span>
                  </label>
                  {coverFile && (
                    <button
                      type="button"
                      onClick={handleRemoveCover}
                      className="px-2.5 py-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 text-xs transition-colors"
                    >
                      {isRtl ? 'تراجع' : 'Revert'}
                    </button>
                  )}
                </div>
                <p className="text-[11px] text-slate-400">
                  {coverFile
                    ? (isRtl ? 'تم تحديد صورة جديدة سيتم حفظها عند النقر على حفظ.' : 'New image selected. Click Save Changes to apply.')
                    : (isRtl ? 'يمكنك استبدال صورة الغلاف الحالية بأي وقت.' : 'Replace or update the current track artwork at any time.')}
                </p>
              </div>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Title (English) *</label>
              <input
                type="text"
                required
                value={titleEn}
                onChange={(e) => setTitleEn(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Title (Arabic)</label>
              <input
                type="text"
                value={titleAr}
                onChange={(e) => setTitleAr(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500 text-right"
              />
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Artist Name</label>
              <input
                type="text"
                value={artistName}
                onChange={(e) => setArtistName(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Genre</label>
              <select
                value={genreId}
                onChange={(e) => setGenreId(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              >
                <option value="">Select Genre</option>
                {genres.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name_en} / {g.name_ar}
                  </option>
                ))}
              </select>
            </div>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Album</label>
              <select
                value={albumId}
                onChange={(e) => setAlbumId(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              >
                <option value="">— Standalone Single —</option>
                {albums.map((a) => (
                  <option key={a.id} value={a.id}>
                    {a.title_en}
                  </option>
                ))}
              </select>
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Track Number</label>
              <input
                type="number"
                min="1"
                value={trackNumber}
                onChange={(e) => setTrackNumber(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Remix Source Track</label>
            <select
              value={originalTrackId}
              onChange={(e) => handleOriginalTrackChange(e.target.value)}
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            >
              <option value="">— None (Original Track) —</option>
              {tracks.filter(t => t.id !== track.id).map((t) => (
                <option key={t.id} value={t.id}>
                  #{t.id} {t.title_en} ({t.artist_name})
                </option>
              ))}
            </select>
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">BPM</label>
              <input
                type="number"
                value={bpm}
                onChange={(e) => setBpm(e.target.value)}
                placeholder="128"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Musical Key</label>
              <input
                type="text"
                value={trackKey}
                onChange={(e) => setTrackKey(e.target.value)}
                placeholder="Am"
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
          </div>

          {/* Rights Declaration */}
          <div className="p-3 rounded-xl bg-slate-800/40 border border-slate-700/60 space-y-2">
            <div className="flex items-center gap-2">
              <ShieldCheck className="w-4 h-4 text-emerald-400" />
              <span className="text-xs font-semibold text-white">
                {isRtl ? 'إقرار حقوق الملكية للمنصة' : 'Platform Rights Declaration'}
              </span>
            </div>
            <select
              value={rightsType}
              onChange={(e) => setRightsType(e.target.value)}
              className="w-full px-3 py-1.5 bg-slate-900 border border-slate-700 rounded text-xs text-slate-200"
            >
              <option value="original_composition">
                {isRtl ? 'عمل موسيقي أصلي (مملوك لـ FUN MOMENT بالكامل)' : 'Original Composition (FUN MOMENT Platform-Owned)'}
              </option>
              <option value="licensed_remix">
                {isRtl ? 'ريمكس مرخّص (منسوب لمسار رئيسي)' : 'Licensed Remix (Attributed to Master Track)'}
              </option>
              <option value="public_domain">
                {isRtl ? 'ملكية عامة / مصرح حقوقياً' : 'Public Domain / Rights-Cleared Composition'}
              </option>
            </select>
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-medium"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-lg text-sm flex items-center gap-2"
            >
              {submitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
              <span>Save Changes</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// -------------------------------------------------------------------------------------
// Create Album Modal Component
// -------------------------------------------------------------------------------------
function CreateAlbumModal({
  genres,
  language,
  onClose,
  onSuccess
}: {
  genres: any[];
  language: Language;
  onClose: () => void;
  onSuccess: (msg: string) => void;
}) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [titleEn, setTitleEn] = useState('');
  const [titleAr, setTitleAr] = useState('');
  const [artistName, setArtistName] = useState('FUN MOMENT Originals');
  const [genreId, setGenreId] = useState<string>(genres[0]?.id ? String(genres[0].id) : '');
  const [descriptionEn, setDescriptionEn] = useState('');
  const [descriptionAr, setDescriptionAr] = useState('');

  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [coverPreview, setCoverPreview] = useState<string | null>(null);
  const isRtl = language === 'ar';

  useEffect(() => {
    return () => {
      if (coverPreview) {
        URL.revokeObjectURL(coverPreview);
      }
    };
  }, [coverPreview]);

  const handleCoverChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
      setError(
        isRtl
          ? 'صيغة الغلاف غير مدعومة. يرجى اختيار ملف صورة بتنسيق JPG أو PNG أو WebP'
          : 'Unsupported cover format. Please select a JPG, PNG, or WebP image.'
      );
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setError(
        isRtl
          ? 'حجم صورة الغلاف يتجاوز الحد الأقصى (٥ ميجابايت)'
          : 'Cover image exceeds the maximum allowed size (5MB).'
      );
      return;
    }

    setError(null);
    setCoverFile(file);
    if (coverPreview) {
      URL.revokeObjectURL(coverPreview);
    }
    setCoverPreview(URL.createObjectURL(file));
  };

  const handleRemoveCover = () => {
    setCoverFile(null);
    if (coverPreview) {
      URL.revokeObjectURL(coverPreview);
      setCoverPreview(null);
    }
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('title_en', titleEn.trim());
      if (titleAr.trim()) formData.append('title_ar', titleAr.trim());
      if (artistName.trim()) formData.append('artist_name', artistName.trim());
      if (genreId) formData.append('genre_id', genreId);
      if (descriptionEn.trim()) formData.append('description_en', descriptionEn.trim());
      if (descriptionAr.trim()) formData.append('description_ar', descriptionAr.trim());
      if (coverFile) formData.append('cover_image', coverFile);

      const res = await api.createMusicAlbum(formData);
      onSuccess(res.message_en);
    } catch (err: any) {
      setError(err.message || 'Creation failed');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
      <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
        <div className="sticky top-0 bg-slate-900 px-6 py-4 border-b border-slate-800 flex justify-between items-center z-10">
          <div className="flex items-center gap-2">
            <Disc className="w-5 h-5 text-amber-400" />
            <h2 className="text-lg font-bold text-white">Create New Album / Collection</h2>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-white">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-xs">
              {error}
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Title (English) *</label>
            <input
              type="text"
              required
              value={titleEn}
              onChange={(e) => setTitleEn(e.target.value)}
              placeholder="e.g. Desert Echoes Vol. 1"
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Title (Arabic)</label>
            <input
              type="text"
              value={titleAr}
              onChange={(e) => setTitleAr(e.target.value)}
              placeholder="مثال: أصداء الصحراء الإصدار ١"
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500 text-right"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Artist Name</label>
              <input
                type="text"
                value={artistName}
                onChange={(e) => setArtistName(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Genre</label>
              <select
                value={genreId}
                onChange={(e) => setGenreId(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              >
                <option value="">Select Genre</option>
                {genres.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name_en}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Album Cover Artwork Upload */}
          <div className="p-3.5 rounded-xl border border-slate-700/80 bg-slate-800/40 space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <ImageIcon className="w-4 h-4 text-amber-400" />
                <label className="text-xs font-semibold text-slate-200">
                  {isRtl ? 'غلاف الألبوم' : 'Album Cover Artwork'}
                </label>
              </div>
              <span className="text-[11px] text-slate-400">JPG, PNG, WebP (Max 5MB)</span>
            </div>

            <div className="flex items-center gap-4">
              {coverPreview ? (
                <div className="relative group w-20 h-20 rounded-xl overflow-hidden border border-amber-500/40 bg-slate-950 flex-shrink-0 shadow-md">
                  <img src={coverPreview} alt="Cover Preview" className="w-full h-full object-cover" />
                  <button
                    type="button"
                    onClick={handleRemoveCover}
                    className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-rose-400 hover:text-rose-300"
                    title={isRtl ? 'إزالة الغلاف' : 'Remove Cover'}
                  >
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              ) : (
                <div className="w-20 h-20 rounded-xl border border-dashed border-slate-600 bg-slate-900/60 flex flex-col items-center justify-center text-slate-500 flex-shrink-0">
                  <ImageIcon className="w-6 h-6 mb-1 text-slate-600" />
                  <span className="text-[10px]">{isRtl ? 'لا يوجد غلاف' : 'No artwork'}</span>
                </div>
              )}

              <div className="flex-1 space-y-2">
                <div className="flex items-center gap-2">
                  <input
                    type="file"
                    id="album-create-cover-upload"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    className="hidden"
                    onChange={handleCoverChange}
                  />
                  <label
                    htmlFor="album-create-cover-upload"
                    className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-600 bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 transition-colors"
                  >
                    <Upload className="w-3.5 h-3.5 text-amber-400" />
                    <span>
                      {coverPreview
                        ? (isRtl ? 'استبدال الغلاف' : 'Replace Artwork')
                        : (isRtl ? 'اختيار صورة الغلاف' : 'Upload Cover Artwork')}
                    </span>
                  </label>
                  {coverPreview && (
                    <button
                      type="button"
                      onClick={handleRemoveCover}
                      className="px-2.5 py-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 text-xs transition-colors"
                    >
                      {isRtl ? 'إزالة' : 'Remove'}
                    </button>
                  )}
                </div>
                <p className="text-[11px] text-slate-400">
                  {isRtl
                    ? 'يُعرض في واجهة المتجر ومكتبة الألبومات وتطبيق المستمعين.'
                    : 'Displayed in store collections, player carousel, and mobile album view.'}
                </p>
              </div>
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Description (English)</label>
            <textarea
              rows={2}
              value={descriptionEn}
              onChange={(e) => setDescriptionEn(e.target.value)}
              placeholder="Album description and curator notes..."
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Description (Arabic)</label>
            <textarea
              rows={2}
              value={descriptionAr}
              onChange={(e) => setDescriptionAr(e.target.value)}
              placeholder="وصف الألبوم وملاحظات الإشراف الفني..."
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500 text-right"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-medium"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-lg text-sm flex items-center gap-2"
            >
              {submitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
              <span>Create Album</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}

// -------------------------------------------------------------------------------------
// Edit Album Modal Component
// -------------------------------------------------------------------------------------
function EditAlbumModal({
  album,
  genres,
  language,
  onClose,
  onSuccess
}: {
  album: MusicAlbumItem;
  genres: any[];
  language: Language;
  onClose: () => void;
  onSuccess: (msg: string) => void;
}) {
  const [submitting, setSubmitting] = useState(false);
  const [error, setError] = useState<string | null>(null);

  const [titleEn, setTitleEn] = useState(album.title_en || '');
  const [titleAr, setTitleAr] = useState(album.title_ar || '');
  const [artistName, setArtistName] = useState(album.artist_name || 'FUN MOMENT');
  const [genreId, setGenreId] = useState<string>(album.genre_id ? String(album.genre_id) : '');
  const [descriptionEn, setDescriptionEn] = useState(album.description_en || '');
  const [descriptionAr, setDescriptionAr] = useState(album.description_ar || '');

  const [coverFile, setCoverFile] = useState<File | null>(null);
  const [removeCover, setRemoveCover] = useState(false);
  const [coverPreview, setCoverPreview] = useState<string | null>(
    album.cover_image_url || (album.cover_image ? (album.cover_image.startsWith('http') ? album.cover_image : `/storage/${album.cover_image}`) : null)
  );
  const isRtl = language === 'ar';

  useEffect(() => {
    return () => {
      if (coverPreview && coverPreview.startsWith('blob:')) {
        URL.revokeObjectURL(coverPreview);
      }
    };
  }, [coverPreview]);

  const handleCoverChange = (e: React.ChangeEvent<HTMLInputElement>) => {
    const file = e.target.files?.[0];
    if (!file) return;

    const validTypes = ['image/jpeg', 'image/png', 'image/webp', 'image/jpg'];
    if (!validTypes.includes(file.type)) {
      setError(
        isRtl
          ? 'صيغة الغلاف غير مدعومة. يرجى اختيار ملف صورة بتنسيق JPG أو PNG أو WebP'
          : 'Unsupported cover format. Please select a JPG, PNG, or WebP image.'
      );
      return;
    }

    if (file.size > 5 * 1024 * 1024) {
      setError(
        isRtl
          ? 'حجم صورة الغلاف يتجاوز الحد الأقصى (٥ ميجابايت)'
          : 'Cover image exceeds the maximum allowed size (5MB).'
      );
      return;
    }

    setError(null);
    setCoverFile(file);
    setRemoveCover(false);
    if (coverPreview && coverPreview.startsWith('blob:')) {
      URL.revokeObjectURL(coverPreview);
    }
    setCoverPreview(URL.createObjectURL(file));
  };

  const handleRemoveCover = () => {
    if (coverPreview && coverPreview.startsWith('blob:')) {
      URL.revokeObjectURL(coverPreview);
    }
    setCoverFile(null);
    setCoverPreview(null);
    setRemoveCover(true);
  };

  const handleResetCover = () => {
    if (coverPreview && coverPreview.startsWith('blob:')) {
      URL.revokeObjectURL(coverPreview);
    }
    setCoverFile(null);
    setRemoveCover(false);
    setCoverPreview(
      album.cover_image_url || (album.cover_image ? (album.cover_image.startsWith('http') ? album.cover_image : `/storage/${album.cover_image}`) : null)
    );
  };

  const handleSubmit = async (e: React.FormEvent) => {
    e.preventDefault();
    setSubmitting(true);
    setError(null);

    try {
      const formData = new FormData();
      formData.append('title_en', titleEn.trim());
      if (titleAr.trim()) formData.append('title_ar', titleAr.trim());
      if (artistName.trim()) formData.append('artist_name', artistName.trim());
      if (genreId) formData.append('genre_id', genreId);
      if (descriptionEn.trim()) formData.append('description_en', descriptionEn.trim());
      if (descriptionAr.trim()) formData.append('description_ar', descriptionAr.trim());

      if (coverFile) {
        formData.append('cover_image', coverFile);
      } else if (removeCover) {
        formData.append('remove_cover', '1');
      }

      const res = await api.updateMusicAlbum(album.id, formData);
      onSuccess(res.message_en);
    } catch (err: any) {
      setError(err.message || 'Update failed');
    } finally {
      setSubmitting(false);
    }
  };

  return (
    <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black/80 backdrop-blur-sm">
      <div className="bg-slate-900 border border-slate-700 rounded-2xl w-full max-w-lg shadow-2xl max-h-[90vh] overflow-y-auto">
        <div className="sticky top-0 bg-slate-900 px-6 py-4 border-b border-slate-800 flex justify-between items-center z-10">
          <div className="flex items-center gap-2">
            <Disc className="w-5 h-5 text-amber-400" />
            <h2 className="text-lg font-bold text-white">Edit Album #{album.id}</h2>
          </div>
          <button onClick={onClose} className="text-slate-400 hover:text-white">
            <X className="w-5 h-5" />
          </button>
        </div>

        <form onSubmit={handleSubmit} className="p-6 space-y-4">
          {error && (
            <div className="p-3 rounded-lg bg-red-500/10 border border-red-500/30 text-red-300 text-xs">
              {error}
            </div>
          )}

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Title (English) *</label>
            <input
              type="text"
              required
              value={titleEn}
              onChange={(e) => setTitleEn(e.target.value)}
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Title (Arabic)</label>
            <input
              type="text"
              value={titleAr}
              onChange={(e) => setTitleAr(e.target.value)}
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500 text-right"
            />
          </div>

          <div className="grid grid-cols-2 gap-4">
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Artist Name</label>
              <input
                type="text"
                value={artistName}
                onChange={(e) => setArtistName(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              />
            </div>
            <div>
              <label className="block text-xs font-semibold text-slate-300 mb-1">Genre</label>
              <select
                value={genreId}
                onChange={(e) => setGenreId(e.target.value)}
                className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
              >
                <option value="">Select Genre</option>
                {genres.map((g) => (
                  <option key={g.id} value={g.id}>
                    {g.name_en}
                  </option>
                ))}
              </select>
            </div>
          </div>

          {/* Album Cover Artwork Upload / Replace / Remove */}
          <div className="p-3.5 rounded-xl border border-slate-700/80 bg-slate-800/40 space-y-3">
            <div className="flex items-center justify-between">
              <div className="flex items-center gap-2">
                <ImageIcon className="w-4 h-4 text-amber-400" />
                <label className="text-xs font-semibold text-slate-200">
                  {isRtl ? 'غلاف الألبوم' : 'Album Cover Artwork'}
                </label>
              </div>
              <span className="text-[11px] text-slate-400">JPG, PNG, WebP (Max 5MB)</span>
            </div>

            <div className="flex items-center gap-4">
              {coverPreview ? (
                <div className="relative group w-20 h-20 rounded-xl overflow-hidden border border-amber-500/40 bg-slate-950 flex-shrink-0 shadow-md">
                  <img src={coverPreview} alt="Cover Preview" className="w-full h-full object-cover" />
                  {coverFile && (
                    <span className="absolute bottom-1 right-1 px-1.5 py-0.5 rounded bg-amber-500 text-slate-950 text-[9px] font-bold">
                      {isRtl ? 'جديد' : 'NEW'}
                    </span>
                  )}
                  <button
                    type="button"
                    onClick={handleRemoveCover}
                    className="absolute inset-0 bg-black/60 opacity-0 group-hover:opacity-100 flex items-center justify-center transition-opacity text-rose-400 hover:text-rose-300"
                    title={isRtl ? 'إزالة الغلاف' : 'Remove Cover'}
                  >
                    <Trash2 className="w-5 h-5" />
                  </button>
                </div>
              ) : (
                <div className="w-20 h-20 rounded-xl border border-dashed border-slate-600 bg-slate-900/60 flex flex-col items-center justify-center text-slate-500 flex-shrink-0">
                  <ImageIcon className="w-6 h-6 mb-1 text-slate-600" />
                  <span className="text-[10px]">
                    {removeCover
                      ? (isRtl ? 'سيتم الحذف' : 'Marked for deletion')
                      : (isRtl ? 'لا يوجد غلاف' : 'No artwork')}
                  </span>
                </div>
              )}

              <div className="flex-1 space-y-2">
                <div className="flex items-center gap-2">
                  <input
                    type="file"
                    id="album-edit-cover-upload"
                    accept="image/jpeg,image/png,image/webp,image/jpg"
                    className="hidden"
                    onChange={handleCoverChange}
                  />
                  <label
                    htmlFor="album-edit-cover-upload"
                    className="cursor-pointer inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg border border-slate-600 bg-slate-800 hover:bg-slate-700 text-xs font-medium text-slate-200 transition-colors"
                  >
                    <Upload className="w-3.5 h-3.5 text-amber-400" />
                    <span>
                      {coverPreview
                        ? (isRtl ? 'استبدال الغلاف' : 'Replace Artwork')
                        : (isRtl ? 'رفع غلاف جديد' : 'Upload Cover Artwork')}
                    </span>
                  </label>
                  {(coverFile || removeCover) && (
                    <button
                      type="button"
                      onClick={handleResetCover}
                      className="px-2.5 py-1.5 rounded-lg border border-slate-600 text-slate-300 hover:bg-slate-800 text-xs transition-colors"
                    >
                      {isRtl ? 'تراجع' : 'Reset'}
                    </button>
                  )}
                  {coverPreview && !coverFile && (
                    <button
                      type="button"
                      onClick={handleRemoveCover}
                      className="px-2.5 py-1.5 rounded-lg border border-red-500/30 text-red-400 hover:bg-red-500/10 text-xs transition-colors"
                    >
                      {isRtl ? 'إزالة الغلاف' : 'Remove Cover'}
                    </button>
                  )}
                </div>
                <p className="text-[11px] text-slate-400">
                  {isRtl
                    ? 'تحديث غلاف الألبوم لن يغير الأغلفة المخصصة للمسارات الفردية.'
                    : 'Updating the album artwork does not overwrite existing custom track covers.'}
                </p>
              </div>
            </div>
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Description (English)</label>
            <textarea
              rows={2}
              value={descriptionEn}
              onChange={(e) => setDescriptionEn(e.target.value)}
              placeholder="Album description and curator notes..."
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500"
            />
          </div>

          <div>
            <label className="block text-xs font-semibold text-slate-300 mb-1">Description (Arabic)</label>
            <textarea
              rows={2}
              value={descriptionAr}
              onChange={(e) => setDescriptionAr(e.target.value)}
              placeholder="وصف الألبوم وملاحظات الإشراف الفني..."
              className="w-full px-3 py-2 bg-slate-800 border border-slate-700 rounded-lg text-sm text-white focus:outline-none focus:border-amber-500 text-right"
            />
          </div>

          <div className="flex justify-end gap-2 pt-4 border-t border-slate-800">
            <button
              type="button"
              onClick={onClose}
              className="px-4 py-2 bg-slate-800 hover:bg-slate-700 text-slate-300 rounded-lg text-sm font-medium"
            >
              Cancel
            </button>
            <button
              type="submit"
              disabled={submitting}
              className="px-5 py-2 bg-amber-500 hover:bg-amber-600 text-slate-950 font-bold rounded-lg text-sm flex items-center gap-2"
            >
              {submitting ? <RefreshCw className="w-4 h-4 animate-spin" /> : <Save className="w-4 h-4" />}
              <span>Save Album</span>
            </button>
          </div>
        </form>
      </div>
    </div>
  );
}
