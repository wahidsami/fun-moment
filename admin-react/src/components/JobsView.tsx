import React, { useState, useEffect } from 'react';
import {
  Briefcase,
  Search,
  RefreshCw,
  Eye,
  X,
  User,
  Clock,
  CheckCircle2,
  AlertCircle,
  FileText,
  DollarSign,
  ChevronRight,
  ChevronDown,
  Globe,
  MapPin,
  Send,
  SlidersHorizontal,
} from 'lucide-react';
import { LaravelAPI } from '../api';
import { Language, UserRole, JobPostItem, JobProposalItem, JobsSummary } from '../types';
import { checkPermission } from '../utils/auditLogger';

interface JobsViewProps {
  language: Language;
  activeRole: UserRole;
}

export default function JobsView({ language, activeRole }: JobsViewProps) {
  const isRtl = language === 'ar';
  const canManage = checkPermission(activeRole, 'manage_services') || checkPermission(activeRole, 'manage_orders');

  const [loading, setLoading] = useState<boolean>(true);
  const [jobs, setJobs] = useState<JobPostItem[]>([]);
  const [summary, setSummary] = useState<JobsSummary | null>(null);
  const [search, setSearch] = useState<string>('');
  const [statusFilter, setStatusFilter] = useState<'all' | 'open' | 'hired' | 'paused'>('all');

  // Proposal drawer state
  const [selectedJob, setSelectedJob] = useState<JobPostItem | null>(null);
  const [proposalsLoading, setProposalsLoading] = useState<boolean>(false);
  const [proposals, setProposals] = useState<JobProposalItem[]>([]);
  const [expandedProposalId, setExpandedProposalId] = useState<number | null>(null);

  // Status edit modal state
  const [editingJob, setEditingJob] = useState<JobPostItem | null>(null);
  const [editStatus, setEditStatus] = useState<number>(1);
  const [editIsJobOn, setEditIsJobOn] = useState<number>(1);
  const [submittingStatus, setSubmittingStatus] = useState<boolean>(false);

  const fetchJobsData = async () => {
    try {
      setLoading(true);
      const res = await LaravelAPI.getJobs(search, statusFilter);
      setSummary(res.metrics);
      setJobs(res.jobs.data || []);
    } catch (err) {
      console.error('Failed to load jobs:', err);
    } finally {
      setLoading(false);
    }
  };

  useEffect(() => {
    fetchJobsData();
  }, [statusFilter]);

  const handleSearchSubmit = (e: React.FormEvent) => {
    e.preventDefault();
    fetchJobsData();
  };

  const openProposalsDrawer = async (job: JobPostItem) => {
    setSelectedJob(job);
    setProposals([]);
    setExpandedProposalId(null);
    setProposalsLoading(true);
    try {
      const data = await LaravelAPI.getJobProposals(job.id);
      setProposals(data);
    } catch (err) {
      console.error('Failed to load proposals:', err);
    } finally {
      setProposalsLoading(false);
    }
  };

  const openStatusModal = (job: JobPostItem) => {
    setEditingJob(job);
    setEditStatus(job.status);
    setEditIsJobOn(job.is_job_on);
  };

  const handleSaveStatus = async () => {
    if (!editingJob) return;
    setSubmittingStatus(true);
    try {
      await LaravelAPI.updateJobStatus(editingJob.id, {
        status: editStatus,
        is_job_on: editIsJobOn,
      });
      setEditingJob(null);
      fetchJobsData();
    } catch (err) {
      console.error('Failed to update job status:', err);
    } finally {
      setSubmittingStatus(false);
    }
  };

  const getStatusBadge = (status: number, isJobOn: number) => {
    if (status === 2) {
      return (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-blue-100 text-blue-800 dark:bg-blue-900/40 dark:text-blue-300">
          {language === 'ar' ? 'تم التوظيف' : 'Hired'}
        </span>
      );
    }
    if (status === 0 || isJobOn === 0) {
      return (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">
          {language === 'ar' ? 'متوقف مؤقتاً' : 'Paused'}
        </span>
      );
    }
    if (status === 3) {
      return (
        <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300">
          {language === 'ar' ? 'ملغي' : 'Cancelled'}
        </span>
      );
    }
    return (
      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300">
        {language === 'ar' ? 'مفتوح للتقديم' : 'Open'}
      </span>
    );
  };

  return (
    <div className={`space-y-6 ${isRtl ? 'rtl' : 'ltr'}`}>
      {/* Top Header */}
      <div className="flex flex-col sm:flex-row justify-between items-start sm:items-center gap-4">
        <div>
          <h1 className="text-2xl font-bold text-slate-900 dark:text-white flex items-center gap-2">
            <Briefcase className="w-7 h-7 text-indigo-600 dark:text-indigo-400" />
            {language === 'ar' ? 'إدارة الوظائف والعطاءات' : 'Jobs & Bidding Hub'}
          </h1>
          <p className="text-sm text-slate-500 dark:text-slate-400 mt-1">
            {language === 'ar'
              ? 'مراقبة طلبات الوظائف المنشورة من المشترين وعروض مقدمي الخدمات وعقود التوظيف'
              : 'Monitor custom job posts from buyers, review seller bidding proposals, and moderate contracts.'}
          </p>
        </div>
        <button
          onClick={fetchJobsData}
          disabled={loading}
          className="flex items-center gap-2 px-3 py-2 text-sm font-medium text-slate-700 bg-white border border-slate-300 rounded-lg hover:bg-slate-50 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 dark:hover:bg-slate-700 transition-colors shadow-sm"
        >
          <RefreshCw className={`w-4 h-4 ${loading ? 'animate-spin' : ''}`} />
          {language === 'ar' ? 'تحديث البيانات' : 'Refresh'}
        </button>
      </div>

      {/* KPI Cards */}
      <div className="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'إجمالي الوظائف' : 'Total Jobs'}
            </p>
            <p className="text-2xl font-bold text-slate-900 dark:text-white mt-1">
              {summary ? summary.total_jobs : '—'}
            </p>
          </div>
          <div className="p-3 bg-indigo-50 dark:bg-indigo-900/30 rounded-xl text-indigo-600 dark:text-indigo-400">
            <Briefcase className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'الوظائف المفتوحة' : 'Open for Bids'}
            </p>
            <p className="text-2xl font-bold text-emerald-600 dark:text-emerald-400 mt-1">
              {summary ? summary.open_jobs : '—'}
            </p>
          </div>
          <div className="p-3 bg-emerald-50 dark:bg-emerald-900/30 rounded-xl text-emerald-600 dark:text-emerald-400">
            <Clock className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'الوظائف الموظفة' : 'Hired / In Progress'}
            </p>
            <p className="text-2xl font-bold text-blue-600 dark:text-blue-400 mt-1">
              {summary ? summary.hired_jobs : '—'}
            </p>
          </div>
          <div className="p-3 bg-blue-50 dark:bg-blue-900/30 rounded-xl text-blue-600 dark:text-blue-400">
            <CheckCircle2 className="w-6 h-6" />
          </div>
        </div>

        <div className="bg-white dark:bg-slate-800 p-5 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex items-center justify-between">
          <div>
            <p className="text-xs font-medium text-slate-500 dark:text-slate-400 uppercase tracking-wider">
              {language === 'ar' ? 'إجمالي العروض / العطاءات' : 'Total Proposals'}
            </p>
            <p className="text-2xl font-bold text-purple-600 dark:text-purple-400 mt-1">
              {summary ? summary.total_proposals : '—'}
            </p>
          </div>
          <div className="p-3 bg-purple-50 dark:bg-purple-900/30 rounded-xl text-purple-600 dark:text-purple-400">
            <FileText className="w-6 h-6" />
          </div>
        </div>
      </div>

      {/* Filter and Search Bar */}
      <div className="bg-white dark:bg-slate-800 p-4 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm flex flex-col md:flex-row gap-4 items-center justify-between">
        <form onSubmit={handleSearchSubmit} className="flex-1 w-full flex items-center gap-2">
          <div className="relative flex-1">
            <Search className={`w-4 h-4 absolute ${isRtl ? 'right-3' : 'left-3'} top-1/2 -translate-y-1/2 text-slate-400`} />
            <input
              type="text"
              value={search}
              onChange={(e) => setSearch(e.target.value)}
              placeholder={language === 'ar' ? 'البحث بالعنوان، معرف الوظيفة، أو المشتري...' : 'Search by title, job ID, or buyer name/email...'}
              className={`w-full ${isRtl ? 'pr-9 pl-4' : 'pl-9 pr-4'} py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg focus:outline-none focus:ring-2 focus:ring-indigo-500 text-slate-900 dark:text-white`}
            />
          </div>
          <button
            type="submit"
            className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors"
          >
            {language === 'ar' ? 'بحث' : 'Search'}
          </button>
        </form>

        <div className="flex items-center gap-2 w-full md:w-auto overflow-x-auto pb-1 md:pb-0">
          {(['all', 'open', 'hired', 'paused'] as const).map((filter) => (
            <button
              key={filter}
              onClick={() => setStatusFilter(filter)}
              className={`px-3 py-1.5 text-xs font-semibold rounded-lg capitalize whitespace-nowrap transition-colors ${
                statusFilter === filter
                  ? 'bg-indigo-600 text-white shadow-sm'
                  : 'bg-slate-100 text-slate-600 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600'
              }`}
            >
              {filter === 'all'
                ? language === 'ar' ? 'الكل' : 'All'
                : filter === 'open'
                ? language === 'ar' ? 'مفتوح' : 'Open'
                : filter === 'hired'
                ? language === 'ar' ? 'تم التوظيف' : 'Hired'
                : language === 'ar' ? 'متوقف' : 'Paused'}
            </button>
          ))}
        </div>
      </div>

      {/* Jobs Table */}
      <div className="bg-white dark:bg-slate-800 rounded-xl border border-slate-200 dark:border-slate-700 shadow-sm overflow-hidden">
        <div className="overflow-x-auto">
          <table className="w-full text-left text-sm text-slate-600 dark:text-slate-300">
            <thead className="bg-slate-50 dark:bg-slate-900/50 text-slate-500 dark:text-slate-400 text-xs uppercase font-semibold border-b border-slate-200 dark:border-slate-700">
              <tr>
                <th className="px-6 py-3.5">#</th>
                <th className="px-6 py-3.5">{language === 'ar' ? 'عنوان الوظيفة' : 'Job Title'}</th>
                <th className="px-6 py-3.5">{language === 'ar' ? 'المشتري' : 'Buyer'}</th>
                <th className="px-6 py-3.5">{language === 'ar' ? 'الميزانية' : 'Budget'}</th>
                <th className="px-6 py-3.5">{language === 'ar' ? 'العروض' : 'Proposals'}</th>
                <th className="px-6 py-3.5">{language === 'ar' ? 'الموعد النهائي' : 'Deadline'}</th>
                <th className="px-6 py-3.5">{language === 'ar' ? 'الحالة' : 'Status'}</th>
                <th className="px-6 py-3.5 text-center">{language === 'ar' ? 'الإجراءات' : 'Actions'}</th>
              </tr>
            </thead>
            <tbody className="divide-y divide-slate-200 dark:divide-slate-700">
              {loading ? (
                <tr>
                  <td colSpan={8} className="px-6 py-12 text-center text-slate-400">
                    <RefreshCw className="w-6 h-6 animate-spin mx-auto mb-2 text-indigo-500" />
                    {language === 'ar' ? 'جاري تحميل الوظائف...' : 'Loading jobs...'}
                  </td>
                </tr>
              ) : jobs.length === 0 ? (
                <tr>
                  <td colSpan={8} className="px-6 py-12 text-center text-slate-400">
                    <Briefcase className="w-8 h-8 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
                    {language === 'ar' ? 'لا توجد وظائف مطابقة للبحث' : 'No jobs found matching criteria'}
                  </td>
                </tr>
              ) : (
                jobs.map((job) => (
                  <tr key={job.id} className="hover:bg-slate-50/50 dark:hover:bg-slate-700/30 transition-colors">
                    <td className="px-6 py-4 font-mono text-xs text-slate-400">#{job.id}</td>
                    <td className="px-6 py-4">
                      <div className="font-semibold text-slate-900 dark:text-white line-clamp-1">
                        {job.title}
                      </div>
                      <div className="flex items-center gap-2 mt-1 text-xs text-slate-400">
                        {job.category && (
                          <span className="bg-slate-100 dark:bg-slate-700 px-2 py-0.5 rounded text-slate-600 dark:text-slate-300">
                            {job.category.name}
                          </span>
                        )}
                        <span className="flex items-center gap-1">
                          {job.is_job_online === 1 ? (
                            <>
                              <Globe className="w-3 h-3 text-sky-500" />
                              {language === 'ar' ? 'عبر الإنترنت' : 'Online'}
                            </>
                          ) : (
                            <>
                              <MapPin className="w-3 h-3 text-emerald-500" />
                              {job.city?.service_city || (language === 'ar' ? 'حضوري' : 'On-Site')}
                            </>
                          )}
                        </span>
                      </div>
                    </td>
                    <td className="px-6 py-4">
                      <div className="font-medium text-slate-800 dark:text-slate-200">
                        {job.buyer?.name || '—'}
                      </div>
                      <div className="text-xs text-slate-400 font-mono">
                        {job.buyer?.email || ''}
                      </div>
                    </td>
                    <td className="px-6 py-4 font-semibold text-slate-900 dark:text-white">
                      {job.price} SAR
                    </td>
                    <td className="px-6 py-4">
                      <span className="inline-flex items-center px-2.5 py-0.5 rounded-full text-xs font-medium bg-purple-100 text-purple-800 dark:bg-purple-900/40 dark:text-purple-300">
                        {job.job_requests_count ?? 0} {language === 'ar' ? 'عرض' : 'bids'}
                      </span>
                    </td>
                    <td className="px-6 py-4 text-xs text-slate-500">
                      {job.dead_line ? new Date(job.dead_line).toLocaleDateString() : '—'}
                    </td>
                    <td className="px-6 py-4">
                      {getStatusBadge(job.status, job.is_job_on)}
                    </td>
                    <td className="px-6 py-4 text-center">
                      <div className="flex items-center justify-center gap-2">
                        <button
                          onClick={() => openProposalsDrawer(job)}
                          className="px-2.5 py-1.5 text-xs font-medium text-indigo-600 bg-indigo-50 hover:bg-indigo-100 dark:bg-indigo-900/30 dark:text-indigo-300 dark:hover:bg-indigo-900/50 rounded-lg transition-colors flex items-center gap-1"
                          title={language === 'ar' ? 'معاينة العروض' : 'View Proposals'}
                        >
                          <Eye className="w-3.5 h-3.5" />
                          {language === 'ar' ? 'العروض' : 'Proposals'}
                        </button>
                        {canManage && (
                          <button
                            onClick={() => openStatusModal(job)}
                            className="px-2 py-1.5 text-xs font-medium text-slate-600 bg-slate-100 hover:bg-slate-200 dark:bg-slate-700 dark:text-slate-300 dark:hover:bg-slate-600 rounded-lg transition-colors flex items-center gap-1"
                            title={language === 'ar' ? 'تعديل الحالة' : 'Moderate Status'}
                          >
                            <SlidersHorizontal className="w-3.5 h-3.5" />
                          </button>
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

      {/* Slide-over Proposal Drawer */}
      {selectedJob && (
        <div className="fixed inset-0 z-50 overflow-hidden bg-slate-900/50 backdrop-blur-xs flex justify-end">
          <div className="w-full max-w-xl bg-white dark:bg-slate-800 shadow-2xl h-full flex flex-col">
            {/* Drawer Header */}
            <div className="p-5 border-b border-slate-200 dark:border-slate-700 flex items-center justify-between">
              <div>
                <span className="text-xs font-mono uppercase text-indigo-600 dark:text-indigo-400">
                  {language === 'ar' ? 'عروض الوظيفة' : 'Job Proposals'} #{selectedJob.id}
                </span>
                <h2 className="text-lg font-bold text-slate-900 dark:text-white line-clamp-1 mt-0.5">
                  {selectedJob.title}
                </h2>
                <div className="flex items-center gap-3 mt-1 text-xs text-slate-500 dark:text-slate-400">
                  <span>{language === 'ar' ? 'الميزانية:' : 'Budget:'} <strong className="text-slate-900 dark:text-white">{selectedJob.price} SAR</strong></span>
                  <span>•</span>
                  <span>{language === 'ar' ? 'المشتري:' : 'Buyer:'} {selectedJob.buyer?.name}</span>
                </div>
              </div>
              <button
                onClick={() => setSelectedJob(null)}
                className="p-2 text-slate-400 hover:text-slate-600 dark:hover:text-slate-200 rounded-lg hover:bg-slate-100 dark:hover:bg-slate-700 transition-colors"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            {/* Drawer Body */}
            <div className="flex-1 overflow-y-auto p-5 space-y-4">
              {proposalsLoading ? (
                <div className="py-20 text-center text-slate-400">
                  <RefreshCw className="w-8 h-8 animate-spin mx-auto mb-2 text-indigo-500" />
                  {language === 'ar' ? 'جاري تحميل العروض المقدمة...' : 'Loading proposals...'}
                </div>
              ) : proposals.length === 0 ? (
                <div className="py-20 text-center text-slate-400">
                  <FileText className="w-10 h-10 mx-auto mb-2 text-slate-300 dark:text-slate-600" />
                  <p className="font-medium text-slate-700 dark:text-slate-300">
                    {language === 'ar' ? 'لم يتم تقديم أي عروض بعد' : 'No proposals submitted yet'}
                  </p>
                  <p className="text-xs text-slate-400 mt-1">
                    {language === 'ar'
                      ? 'سيظهر هنا أي عرض يقدمه مزودو الخدمات على هذا المنشور'
                      : 'Bids submitted by registered service providers will appear here.'}
                  </p>
                </div>
              ) : (
                proposals.map((proposal) => {
                  const isExpanded = expandedProposalId === proposal.id;
                  return (
                    <div
                      key={proposal.id}
                      className={`p-4 rounded-xl border transition-all ${
                        proposal.is_hired === 1
                          ? 'border-emerald-300 bg-emerald-50/30 dark:border-emerald-700 dark:bg-emerald-950/20'
                          : 'border-slate-200 dark:border-slate-700 bg-white dark:bg-slate-800'
                      }`}
                    >
                      <div className="flex items-start justify-between gap-3">
                        <div className="flex items-center gap-3">
                          <div className="w-10 h-10 rounded-full bg-indigo-100 dark:bg-indigo-900/50 flex items-center justify-center text-indigo-600 dark:text-indigo-400 font-bold">
                            {proposal.seller?.name?.charAt(0) || 'S'}
                          </div>
                          <div>
                            <div className="font-semibold text-slate-900 dark:text-white flex items-center gap-2">
                              {proposal.seller?.name || 'Seller'}
                              {proposal.is_hired === 1 && (
                                <span className="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-semibold bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300">
                                  {language === 'ar' ? 'تم التوظيف' : 'Hired'}
                                </span>
                              )}
                            </div>
                            <p className="text-xs text-slate-400 font-mono">
                              {proposal.seller?.email}
                            </p>
                          </div>
                        </div>
                        <div className="text-right">
                          <div className="text-base font-bold text-slate-900 dark:text-white">
                            {proposal.expected_salary} SAR
                          </div>
                          <span className="text-xs text-slate-400">
                            {new Date(proposal.created_at).toLocaleDateString()}
                          </span>
                        </div>
                      </div>

                      {/* Cover letter */}
                      <div className="mt-3 p-3 bg-slate-50 dark:bg-slate-900/50 rounded-lg text-xs text-slate-600 dark:text-slate-300 whitespace-pre-line border border-slate-100 dark:border-slate-800">
                        <span className="font-semibold text-slate-500 block mb-1">
                          {language === 'ar' ? 'خطاب التقديم / الشرح:' : 'Cover Letter / Pitch:'}
                        </span>
                        {proposal.cover_letter || '—'}
                      </div>

                      {/* Messages / Negotiation Accordion */}
                      {proposal.conversations && proposal.conversations.length > 0 && (
                        <div className="mt-3">
                          <button
                            onClick={() => setExpandedProposalId(isExpanded ? null : proposal.id)}
                            className="flex items-center justify-between w-full text-xs font-medium text-slate-500 hover:text-indigo-600 dark:hover:text-indigo-400 transition-colors"
                          >
                            <span>
                              {language === 'ar' ? 'سجل المحادثة والتفاوض' : 'Negotiation Messages'} ({proposal.conversations.length})
                            </span>
                            {isExpanded ? <ChevronDown className="w-3.5 h-3.5" /> : <ChevronRight className="w-3.5 h-3.5" />}
                          </button>

                          {isExpanded && (
                            <div className="mt-2 space-y-2 p-3 bg-slate-100/50 dark:bg-slate-900/80 rounded-lg">
                              {proposal.conversations.map((msg) => (
                                <div
                                  key={msg.id}
                                  className={`p-2.5 rounded-lg text-xs max-w-[85%] ${
                                    msg.type === 'buyer'
                                      ? 'bg-indigo-600 text-white ml-auto'
                                      : 'bg-white dark:bg-slate-800 text-slate-800 dark:text-slate-200 border border-slate-200 dark:border-slate-700'
                                  }`}
                                >
                                  <div className="flex justify-between items-center mb-1 text-[10px] opacity-75 font-semibold">
                                    <span>{msg.type === 'buyer' ? 'Buyer' : 'Seller'}</span>
                                    <span>{new Date(msg.created_at).toLocaleTimeString([], { hour: '2-digit', minute: '2-digit' })}</span>
                                  </div>
                                  <p>{msg.message}</p>
                                </div>
                              ))}
                            </div>
                          )}
                        </div>
                      )}
                    </div>
                  );
                })
              )}
            </div>
          </div>
        </div>
      )}

      {/* Status Moderation Modal */}
      {editingJob && (
        <div className="fixed inset-0 z-50 flex items-center justify-center p-4 bg-slate-900/50 backdrop-blur-xs">
          <div className="bg-white dark:bg-slate-800 rounded-xl shadow-xl max-w-md w-full p-6 border border-slate-200 dark:border-slate-700">
            <div className="flex items-center justify-between pb-3 border-b border-slate-200 dark:border-slate-700">
              <h3 className="text-lg font-bold text-slate-900 dark:text-white flex items-center gap-2">
                <SlidersHorizontal className="w-5 h-5 text-indigo-600" />
                {language === 'ar' ? 'تعديل حالة الوظيفة' : 'Moderate Job Status'}
              </h3>
              <button
                onClick={() => setEditingJob(null)}
                className="text-slate-400 hover:text-slate-600 dark:hover:text-slate-200"
              >
                <X className="w-5 h-5" />
              </button>
            </div>

            <div className="mt-4 space-y-4">
              <div>
                <p className="text-xs text-slate-400 uppercase font-semibold">
                  {language === 'ar' ? 'الوظيفة:' : 'Job Title:'}
                </p>
                <p className="font-medium text-slate-900 dark:text-white text-sm mt-0.5">
                  {editingJob.title}
                </p>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                  {language === 'ar' ? 'حالة الوظيفة' : 'Job Status'}
                </label>
                <select
                  value={editStatus}
                  onChange={(e) => setEditStatus(Number(e.target.value))}
                  className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value={1}>{language === 'ar' ? 'مفتوح (نشط)' : 'Open (Active)'}</option>
                  <option value={0}>{language === 'ar' ? 'معلق / متوقف' : 'Draft / Paused'}</option>
                  <option value={2}>{language === 'ar' ? 'تم التوظيف / قيد التنفيذ' : 'Hired / In Progress'}</option>
                  <option value={3}>{language === 'ar' ? 'ملغي' : 'Cancelled'}</option>
                </select>
              </div>

              <div>
                <label className="block text-xs font-semibold text-slate-600 dark:text-slate-300 uppercase mb-1">
                  {language === 'ar' ? 'استقبال العروض الجديدة' : 'Accepting New Proposals'}
                </label>
                <select
                  value={editIsJobOn}
                  onChange={(e) => setEditIsJobOn(Number(e.target.value))}
                  className="w-full px-3 py-2 text-sm bg-slate-50 dark:bg-slate-900 border border-slate-300 dark:border-slate-700 rounded-lg text-slate-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-indigo-500"
                >
                  <option value={1}>{language === 'ar' ? 'مفعل (استقبال العطاءات)' : 'Active (Accepting bids)'}</option>
                  <option value={0}>{language === 'ar' ? 'مغلق (إيقاف العطاءات)' : 'Closed (No new bids)'}</option>
                </select>
              </div>
            </div>

            <div className="mt-6 flex justify-end gap-3 pt-3 border-t border-slate-200 dark:border-slate-700">
              <button
                type="button"
                onClick={() => setEditingJob(null)}
                className="px-4 py-2 text-sm font-medium text-slate-600 hover:text-slate-800 dark:text-slate-300 transition-colors"
              >
                {language === 'ar' ? 'إلغاء' : 'Cancel'}
              </button>
              <button
                type="button"
                onClick={handleSaveStatus}
                disabled={submittingStatus}
                className="px-4 py-2 text-sm font-medium text-white bg-indigo-600 hover:bg-indigo-700 rounded-lg shadow-sm transition-colors flex items-center gap-2"
              >
                {submittingStatus && <RefreshCw className="w-4 h-4 animate-spin" />}
                {language === 'ar' ? 'حفظ التغييرات' : 'Save Changes'}
              </button>
            </div>
          </div>
        </div>
      )}
    </div>
  );
}
