<?php

return [

    // General
    'approval' => 'موافقة',
    'approvals' => 'الموافقات',

    // Navigation
    'navigation_group' => 'الموافقات',
    'flow_resource_label' => 'مسار الموافقة',
    'flow_resource_plural' => 'مسارات الموافقة',

    // Statuses
    'status' => [
        'pending' => 'قيد الانتظار',
        'approved' => 'تمت الموافقة',
        'rejected' => 'مرفوض',
        'cancelled' => 'ملغي',
    ],

    // Step types
    'step_type' => [
        'single' => 'موافق واحد',
        'sequential' => 'تسلسلي',
        'parallel' => 'متوازي',
    ],

    // Action types
    'action_type' => [
        'submitted' => 'تم الإرسال',
        'approved' => 'تمت الموافقة',
        'rejected' => 'مرفوض',
        'commented' => 'تم التعليق',
        'delegated' => 'تم التفويض',
        'escalated' => 'تم التصعيد',
        'returned' => 'تم الإرجاع',
    ],

    // Step instance statuses
    'step_status' => [
        'pending' => 'قيد الانتظار',
        'waiting' => 'في الانتظار',
        'approved' => 'تمت الموافقة',
        'rejected' => 'مرفوض',
        'skipped' => 'تم التخطي',
    ],

    // Escalation actions
    'escalation' => [
        'notify' => 'إرسال تذكير',
        'auto_approve' => 'موافقة تلقائية',
        'reassign' => 'إعادة تعيين',
        'reject' => 'رفض تلقائي',
    ],

    // Resolver labels
    'resolvers' => [
        'user' => 'مستخدمون محددون',
        'role' => 'مستخدمون حسب الدور',
        'custom_rule' => 'قاعدة مخصصة',
    ],

    // Flow resource form
    'flow' => [
        'flow_details' => 'تفاصيل المسار',
        'name' => 'الاسم',
        'description' => 'الوصف',
        'applies_to' => 'ينطبق على',
        'any_model' => 'أي نموذج',
        'applies_to_helper' => 'اتركه فارغاً ليُطبّق على أي نموذج',
        'is_active' => 'نشط',
        'approval_steps' => 'خطوات الموافقة',
        'step_name' => 'اسم الخطوة',
        'type' => 'النوع',
        'approver_type' => 'نوع الموافق',
        'required_approvals' => 'الموافقات المطلوبة',
        'required_approvals_hint' => 'مطلوب :required من أصل :total موافقين',
        'required_approvals_helper' => 'كم عدد الموافقين المطلوبين لاعتماد هذه الخطوة',
        'sla_hours' => 'المهلة (ساعات)',
        'sla_helper' => 'اتركه فارغاً بدون مهلة',
        'escalation_action' => 'إجراء التصعيد',
        'add_step' => 'إضافة خطوة',
        'action_key' => 'الإجراء',
        'any_action' => 'أي إجراء',
        'action_key_helper' => 'اختياري. عند التعبئة، يُطبّق المسار فقط على هذا الإجراء. عند تركه فارغاً، يمكن استخدام المسار لأي إجراء.',
        'select_model_first' => 'اختر نموذجاً أولاً لعرض الإجراءات المتاحة.',
    ],

    // Flow resource table
    'flow_table' => [
        'name' => 'الاسم',
        'model' => 'النموذج',
        'any' => 'الكل',
        'steps' => 'الخطوات',
        'is_active' => 'نشط',
        'created_at' => 'تاريخ الإنشاء',
        'action_key' => 'الإجراء',
    ],

    // Common field labels
    'fields' => [
        'status' => 'الحالة',
        'type' => 'النوع',
        'comment' => 'التعليق',
        'submitted_at' => 'تاريخ الإرسال',
        'completed_at' => 'تاريخ الإنجاز',
    ],

    // Actions
    'actions' => [
        'submit' => 'إرسال للموافقة',
        'approve' => 'موافقة',
        'reject' => 'رفض',
        'comment' => 'تعليق',
        'delegate' => 'تفويض',

        'approval_flow' => 'مسار الموافقة',
        'approval_action' => 'الإجراء المطلوب',
        'approval_action_helper' => 'اختر سيناريو العمل الذي يتم إرساله. سيحاول النظام استخدام مسار مخصص لهذا الإجراء ويعود للمسار العام عند عدم وجوده.',
        'comment_optional' => 'تعليق (اختياري)',
        'rejection_reason' => 'سبب الرفض',
        'delegate_to' => 'تفويض إلى',
        'reason' => 'السبب',

        'approve_heading' => 'هل تريد الموافقة على هذا السجل؟',
        'reject_heading' => 'هل تريد رفض هذا السجل؟',

        // Success messages
        'submitted_success' => 'تم الإرسال للموافقة',
        'approved_success' => 'تمت الموافقة',
        'rejected_success' => 'تم الرفض',
        'comment_success' => 'تمت إضافة التعليق',
        'delegated_success' => 'تم التفويض بنجاح',
    ],

    // Notifications
    'notifications' => [
        'requested_title' => 'طلب موافقة: :step',
        'requested_body' => ':model #:id يتطلب موافقتك.',
        'approved_title' => 'اكتملت الموافقة',
        'approved_body' => ':model #:id تمت الموافقة عليه.',
        'rejected_title' => 'تم رفض الموافقة',
        'rejected_body' => ':model #:id تم رفضه.',
        'cancelled_title' => 'تم إلغاء الموافقة',
        'cancelled_body' => 'تم إلغاء موافقة :model #:id.',
        'escalated_title' => 'انتهاء مهلة الموافقة',
        'escalated_body' => ':model #:id تجاوز المهلة المحددة.',
        'sla_warning_title' => 'تنبيه: اقتراب موعد الموافقة',
        'sla_warning_body' => ':model #:id موعد الموافقة :deadline.',
        'view_record' => 'عرض السجل',
    ],

    // Widgets
    'widgets' => [
        'pending_heading' => 'موافقاتي المعلّقة',
        'step' => 'الخطوة',
        'record' => 'السجل',
        'since' => 'منذ',
        'due' => 'الموعد',
        'no_sla' => 'بدون مهلة',
        'pending_approvals' => 'الموافقات المعلّقة',
        'approved_30d' => 'تمت الموافقة (30 يوم)',
        'rejected_30d' => 'مرفوضة (30 يوم)',
        'overdue_steps' => 'خطوات متأخرة',
    ],

    // Relation manager
    'relation_manager' => [
        'title' => 'الموافقات',
        'flow' => 'المسار',
        'submitted_by' => 'مُرسل بواسطة',
        'in_progress' => 'قيد التنفيذ',
        'approval_details' => 'تفاصيل الموافقة',
        'steps' => 'الخطوات',
        'audit_trail' => 'سجل المراجعة',
        'approvers' => 'الموافقون',
        'received_required' => 'المستلمة / المطلوبة',
        'by' => 'بواسطة',
        'system' => 'النظام',
        'date' => 'التاريخ',
        'close' => 'إغلاق',
        'approval_heading' => 'موافقة: :flow',
        'not_available' => 'غير متاح',
    ],

    // Infolist section
    'infolist' => [
        'approval_status' => 'حالة الموافقة',
        'status' => 'الحالة',
        'flow' => 'المسار',
        'submitted_by' => 'مُرسل بواسطة',
        'submitted' => 'تاريخ الإرسال',
        'completed' => 'تاريخ الإنجاز',
        'not_submitted' => 'لم يُرسل',
        'in_progress' => 'قيد التنفيذ',
        'current_step' => 'الخطوة الحالية',
        'step' => 'الخطوة',
        'pending_approvers' => 'الموافقون المعلّقون',
        'progress' => 'التقدم',
        'approvals_count' => ':received / :required موافقات',
        'sla_deadline' => 'الموعد النهائي',
        'no_sla' => 'بدون مهلة',
        'recent_activity' => 'النشاط الأخير',
        'by' => 'بواسطة',
        'system' => 'النظام',
        'date' => 'التاريخ',
        'no_approval' => 'لا توجد موافقة',
        'not_available' => 'غير متاح',
        'rejection_reason' => 'سبب الرفض',
    ],

    // Status column
    'column' => [
        'label' => 'الموافقة',
        'no_approval' => 'لا توجد موافقة',
    ],

    // Resolver config
    'resolver_config' => [
        'users' => 'المستخدمون',
        'role' => 'الأدوار',
        'custom_rule' => 'قاعدة مخصصة',
    ],

    'flow_hints' => [
        'name' => 'اسم المسار الداخلي. استخدم عنواناً واضحاً لتسهيل التعرف عليه.',
        'description' => 'وصف اختياري لتوضيح متى يجب استخدام هذا المسار.',
        'applies_to' => 'يحدد نوع السجل الذي يمكنه استخدام هذا المسار. اتركه فارغاً للسماح لأي نموذج.',
        'action_key' => 'استخدم هذا الحقل لتقييد المسار بإجراء محدد عند وجود سيناريوهات موافقة متعددة.',
        'is_active' => 'عطّله لإيقاف الطلبات الجديدة دون فقدان الإعدادات.',
        'steps' => 'سجّل الخطوات بالترتيب المطلوب للموافقة. كل عنصر يحدد من يوافق وبأي صيغة وبأي مهلة.',
        'step_name' => 'الاسم المعروض للمستخدمين وفي سجل المراجعة.',
        'type' => 'اختر ما إذا كانت الخطوة تستخدم موافقاً واحداً أو سلسلة تسلسلية أو موافقات متوازية.',
        'approver_type' => 'يحدد القاعدة المستخدمة لتحديد الموافقين لهذه الخطوة.',
        'required_approvals' => 'للخطوات المتوازية، هذا هو عدد الموافقات المطلوبة لإكمال الخطوة.',
        'sla_hours' => 'أقصى وقت للاستجابة بالساعات. اتركه فارغاً لتعطيل المهلة.',
        'escalation_action' => 'الإجراء التلقائي عند انتهاء المهلة.',
        'resolver_users' => 'اختر المستخدمين المحددين الذين يمكنهم الموافقة على هذه الخطوة.',
        'resolver_role' => 'جميع المستخدمين الذين لديهم أي من الأدوار المحددة سيعتبرون موافقين لهذه الخطوة.',
        'resolver_custom_rule' => 'اختر القاعدة التي تحدد الموافقين ديناميكياً.',
    ],

    'select' => [
        'search_prompt' => 'اكتب للبحث',
        'no_options' => 'لا توجد خيارات متاحة',
        'no_search_results' => 'لم يتم العثور على نتائج',
        'loading' => 'جاري التحميل...',
    ],

    // Tabs (List page)
    'tabs' => [
        'all' => 'الكل',
        'active' => 'نشط',
        'inactive' => 'غير نشط',
        'pending' => 'معلّقاتي',
    ],

    // SLA command
    'sla' => [
        'auto_approved' => 'تمت الموافقة تلقائياً بسبب تجاوز المهلة',
        'auto_rejected' => 'تم الرفض تلقائياً بسبب تجاوز المهلة',
    ],

];
