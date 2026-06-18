/**
 * Dashboard Stats Types
 *
 * TypeScript interfaces for the PHP-injected dashboard stats data
 * (window.gf_spam_hexer_plugin_settings_strings.dashboardStats).
 */

export interface SummaryData {
	total_checked: number;
	spam_count: number;
	clean_count: number;
}

export interface DifficultyBucket {
	difficulty: number;
	count: number;
	avg_ms: number;
}

export interface PowData {
	avg_solve_ms: number;
	min_solve_ms: number;
	max_solve_ms: number;
	total_solves: number;
	difficulty_distribution: DifficultyBucket[];
	fallback_count: number;
	rest_count: number;
}

export interface AiData {
	avg_latency_ms: number;
	total_cost: number;
	total_ai_calls: number;
	classified_ham?: number;
	classified_spam?: number;
}

export interface PerFormData {
	form_id: number;
	form_title: string;
	total_checked: number;
	spam_count: number;
	spam_rate: number;
	last_spam: string | null;
}

export interface ActionData {
	action: string;
	count: number;
}

export interface SignalData {
	signal: string;
	count: number;
}

export interface ReasonData {
	reason: string;
	count: number;
	avg_probability: number;
}

export interface CommentData {
	total_checked: number;
	spam_count: number;
	clean_count: number;
}

export interface DashboardStatsData {
	period_days: number;
	generated_at: number;
	summary: SummaryData;
	pow: PowData;
	ai: AiData | null;
	per_form: PerFormData[];
	actions: ActionData[];
	pow_signals: SignalData[];
	ai_reasons: ReasonData[];
	ai_signals: SignalData[];
	comments?: CommentData;
}
