export type Language = 'ar' | 'en';

export interface TranslationSchema {
  nav: {
    home: string;
    privacy: string;
    support: string;
    getApp: string;
    switchLang: string;
    switchLangTarget: string;
  };
  hero: {
    kicker: string;
    slides: Array<{
      tag: string;
      title: string;
      subtitle: string;
      accentColor: 'cyan' | 'amber' | 'pink';
    }>;
    primaryCta: string;
    secondaryCta: string;
    availableOn: string;
  };
  intro: {
    badge: string;
    heading: string;
    paragraph: string;
    benefits: Array<{
      title: string;
      description: string;
      color: 'cyan' | 'amber' | 'pink';
    }>;
  };
  appCta: {
    title: string;
    subtitle: string;
    appStore: string;
    googlePlay: string;
  };
  support: {
    badge: string;
    title: string;
    subtitle: string;
    contactTitle: string;
    contactSubtitle: string;
    form: {
      name: string;
      namePlaceholder: string;
      email: string;
      emailPlaceholder: string;
      phone: string;
      phonePlaceholder: string;
      topic: string;
      topics: {
        account: string;
        technical: string;
        experiences: string;
        deletion: string;
        general: string;
      };
      message: string;
      messagePlaceholder: string;
      submit: string;
      submitting: string;
      successTitle: string;
      successMessage: string;
      errorRequired: string;
      errorEmail: string;
      errorMessage: string;
      errorNetwork: string;
    };
    topicsTitle: string;
    topicsSubtitle: string;
    topicsList: Array<{
      title: string;
      description: string;
    }>;
    deletionTitle: string;
    deletionSubtitle: string;
    deletionSteps: Array<string>;
    deletionWebNote: string;
    emailLabel: string;
  };
  privacy: {
    badge: string;
    title: string;
    subtitle: string;
    lastUpdated: string;
    tableOfContents: string;
    sections: Array<{
      id: string;
      title: string;
      content: string[];
    }>;
  };
  footer: {
    copyright: string;
    allRightsReserved: string;
  };
}

export const translations: Record<Language, TranslationSchema> = {
  ar: {
    nav: {
      home: "الرئيسية",
      privacy: "سياسة الخصوصية",
      support: "الدعم والمساعدة",
      getApp: "حمّل التطبيق",
      switchLang: "English",
      switchLangTarget: "en",
    },
    hero: {
      kicker: "تطبيق الترفيه والتجارب الممتعة",
      slides: [
        {
          tag: "لحظات لا تُنسى",
          title: "اجعل كل لحظة ذات قيمة.",
          subtitle: "اكتشف أمتع التجارب والفعاليات الترفيهية وعش لحظات مميزة مع تطبيق FUN MOMENT.",
          accentColor: "cyan",
        },
        {
          tag: "مشاركة وتفاعل",
          title: "لحظات ممتعة تستحق المشاركة.",
          subtitle: "استكشف الأجواء الحيوية والأنشطة الترفيهية وشارك المتعة مع من تحب بكل سهولة وسلاسة.",
          accentColor: "amber",
        },
        {
          tag: "تجارب متجددة",
          title: "لحظتك القادمة تبدأ هنا.",
          subtitle: "وجهتك الرقمية الحديثة لاستكشاف الفعاليات الترفيهية وصنع ذكريات استثنائية.",
          accentColor: "pink",
        },
      ],
      primaryCta: "حمّل التطبيق",
      secondaryCta: "الدعم والمساعدة",
      availableOn: "متوفر على أنظمة iOS و Android",
    },
    intro: {
      badge: "عن التطبيق",
      heading: "ما هو FUN MOMENT؟",
      paragraph: "FUN MOMENT هو تطبيق رقمي حديث صُمم لمساعدتك على استكشاف الفعاليات والأنشطة الترفيهية والتجارب الحية، وصنع لحظات ممتعة مع أصدقائك وعائلتك في بيئة تفاعلية سلسة وممتعة.",
      benefits: [
        {
          title: "استكشاف سلس وسريع",
          description: "تصفح الفعاليات والأنشطة الترفيهية القريبة منك بكل بساطة وسرعة.",
          color: "cyan",
        },
        {
          title: "واجهة تفاعلية حديثة",
          description: "تصميم أنيق وتجربة استخدام عصرية تعكس روح الشباب والحيوية.",
          color: "amber",
        },
        {
          title: "لحظات تستحق التذكر",
          description: "خطط لأوقات فراغك ونشاطاتك المفضلة واستمتع بكل لحظة.",
          color: "pink",
        },
      ],
    },
    appCta: {
      title: "حمّل تطبيق FUN MOMENT الآن",
      subtitle: "ابدأ رحلتك واستمتع بأروع اللحظات والأنشطة الترفيهية.",
      appStore: "App Store",
      googlePlay: "Google Play",
    },
    support: {
      badge: "خدمة المستخدمين",
      title: "الدعم والمساعدة",
      subtitle: "فريق دعم FUN MOMENT جاهز لمساعدتك والإجابة على كافة استفساراتك.",
      contactTitle: "تواصل مع فريق الدعم",
      contactSubtitle: "أرسل استفسارك وسيقوم فريق الدعم بالرد عليك في أقرب وقت ممكن.",
      form: {
        name: "الاسم الكامل",
        namePlaceholder: "سعود بن فهد",
        email: "البريد الإلكتروني",
        emailPlaceholder: "name@example.com",
        phone: "رقم الجوال (اختياري)",
        phonePlaceholder: "+966 5X XXX XXXX",
        topic: "موضوع الاستفسار",
        topics: {
          account: "الحساب وتجربة تسجيل الدخول",
          technical: "مشكلة تقنية داخل التطبيق",
          experiences: "استفسار عن الفعاليات والتجارب",
          deletion: "طلب حذف الحساب والبيانات",
          general: "استفسار عام",
        },
        message: "الرسالة",
        messagePlaceholder: "اكتب تفاصيل استفسارك هنا...",
        submit: "إرسال الرسالة",
        submitting: "جاري الإرسال...",
        successTitle: "تم استلام رسالتك بنجاح!",
        successMessage: "شكراً لتواصلك مع FUN MOMENT. سنقوم بمراجعة طلبك والرد عليك عبر بريدك الإلكتروني قريباً.",
        errorRequired: "هذا الحقل مطلوب.",
        errorEmail: "يرجى إدخال عنوان بريد إلكتروني صالح.",
        errorMessage: "تعذر إرسال الرسالة عبر الخادم. يرجى المحاولة لاحقاً أو مراسلتنا مباشرة عبر البريد الإلكتروني.",
        errorNetwork: "تعذر الاتصال بالخادم. يرجى التحقق من اتصالك بالإنترنت والمحاولة مجدداً.",
      },
      topicsTitle: "موضوعات المساعدة الشائعة",
      topicsSubtitle: "إرشادات عامة لمساعدتك في استخدام التطبيق.",
      topicsList: [
        {
          title: "إدارة الحساب وتسجيل الدخول",
          description: "مساعدة في إنشاء حسابك، تحديث البيانات الشخصية، أو معالجة مشكلات تسجيل الدخول.",
        },
        {
          title: "المشكلات التقنية والتحديثات",
          description: "حل مشكلات تحميل المحتوى، تفعيل الإشعارات، وتحديث التطبيق لأحدث إصدار.",
        },
        {
          title: "استكشاف التجارب والمحتوى",
          description: "إرشادات حول استعراض تفاصيل الفعاليات والأنشطة الترفيهية المعروضة داخل التطبيق.",
        },
      ],
      deletionTitle: "حذف الحساب والبيانات الشخصية",
      deletionSubtitle: "نحن نمنحك التحكم الكامل في بياناتك وحسابك في أي وقت.",
      deletionSteps: [
        "افتح تطبيق FUN MOMENT على هاتفك.",
        "انتقل إلى الملف الشخصي (Profile) ثم الإعدادات (Settings).",
        "اختر خيار 'حسابي' (Account) ثم اضغط على 'حذف الحساب' (Delete Account).",
        "أكّد طلب الحذف وسيتم جدولة حذف الحساب وبياناتك الشخصية المرتبطة به بشكل نهائي.",
      ],
      deletionWebNote: "إذا تعذر عليك الوصول إلى هاتفك أو واجهت أي صعوبة في تنفيذ الخطوات داخل التطبيق، يمكنك اختيار موضوع 'طلب حذف الحساب والبيانات' في النموذج أعلاه أو مراسلتنا مباشرة على بريد الدعم، وسنتولى معالجة طلبك.",
      emailLabel: "البريد الإلكتروني المباشر للدعم:",
    },
    privacy: {
      badge: "الخصوصية والشفافية",
      title: "سياسة الخصوصية",
      subtitle: "نلتزم في FUN MOMENT بحماية خصوصيتك ومعالجة بياناتك بمسؤولية وشفافية.",
      lastUpdated: "آخر تحديث:",
      tableOfContents: "فهرس السياسة",
      sections: [
        {
          id: "intro",
          title: "١. مقدمة",
          content: [
            "توضح سياسة الخصوصية هذه كيفية قيام تطبيق FUN MOMENT ('التطبيق' أو 'نحن') بجمع البيانات الشخصية واستخدامها وحمايتها عند استخدامك لخدماتنا.",
            "باستخدامك للتطبيق، فإنك توافق على ممارسات جمع واستخدام المعلومات الموضحة في هذه الوثيقة.",
          ],
        },
        {
          id: "collection",
          title: "٢. المعلومات التي نجمعها",
          content: [
            "نجمع المعلومات التي تقدمها لنا مباشرة عند استخدام التطبيق:",
            "• معلومات الحساب: مثل الاسم، البريد الإلكتروني، ورقم الجوال عند إنشاء حساب أو تعديل الملف الشخصي.",
            "• معلومات التفاعل مع التطبيق: سجل تفاعلك مع الفعاليات والتجارب الترفيهية التي تستعرضها أو تحفظها.",
            "• المعلومات التقنية ومعلومات الجهاز: نوع الجهاز، نظام التشغيل، ومعرفات التشخيص التقنية لتحسين أداء واستقرار التطبيق.",
          ],
        },
        {
          id: "usage",
          title: "٣. كيف نستخدم معلوماتك",
          content: [
            "نستخدم المعلومات المجمعة للأغراض التالية:",
            "• تمكينك من استخدام خدمات التطبيق وتخصيص تجربتك الترفيهية.",
            "• تقديم خدمات الدعم الفني والرد على استفساراتك وطلباتك.",
            "• إرسال تنبيهات هامة متعلقة بتحديثات الخدمة أو أمان الحساب.",
            "• صيانة التطبيق وتحسين أدائه وموثوقيته التقنية.",
          ],
        },
        {
          id: "sharing",
          title: "٤. مشاركة البيانات والأطراف الثالثة",
          content: [
            "نحن لا نقوم ببيع أو تأجير بياناتك الشخصية لأي طرف ثالث.",
            "قد نشارك بياناتك في حدود ضيقة مع مزودي الخدمات التقنية الموثوقين (مثل خدمات استضافة الخوادم وإرسال رسائل التحقق) بالقدر اللازم لتشغيل الخدمة فقط، ومع الجهات النظامية المختصة إذا طُلب ذلك بموجب الأنظمة والقوانين المعمول بها.",
            "معالجة نموذج الدعم والمساعدة: تُعالج رسائل واستفسارات الدعم المرسلة عبر النموذج الإلكتروني على هذا الموقع بواسطة خدمة Web3Forms كوسيط تقني لنقل الاستفسارات إلى البريد الإلكتروني المخصص لدعم FUN MOMENT (funmomentsproject@gmail.com). تخضع هذه المعالجة لسياسة خصوصية Web3Forms: https://web3forms.com/privacy",
          ],
        },
        {
          id: "security",
          title: "٥. أمان البيانات",
          content: [
            "نطبق إجراءات أمنية تقنية وتنظيمية معتمدة لحماية بياناتك الشخصية من الوصول غير المصرح به أو التغيير أو الفقدان.",
          ],
        },
        {
          id: "retention",
          title: "٦. الاحتفاظ بالبيانات",
          content: [
            "نحتفظ ببياناتك الشخصية طالما كان حسابك نشطاً أو بالقدر اللازم لتقديم الخدمات لك والوفاء بالتزاماتنا القانونية.",
          ],
        },
        {
          id: "deletion",
          title: "٧. حذف الحساب والبيانات",
          content: [
            "يحق لك في أي وقت طلب حذف حسابك وكافة البيانات الشخصية المرتبطة به.",
            "يمكنك حذف حسابك مباشرة من داخل إعدادات التطبيق (الملف الشخصي > الإعدادات > حذف الحساب)، أو عن طريق تقديم طلب حذف عبر صفحة الدعم والمساعدة المتاحة في هذا الموقع.",
            "عند تأكيد طلب الحذف، نقوم بإزالة بيانات حسابك بشكل دائم وفقاً للإجراءات التقنية المعتمدة.",
          ],
        },
        {
          id: "rights",
          title: "٨. حقوق المستخدم",
          content: [
            "يحق لك الاطلاع على بياناتك الشخصية المخزنة لدينا، طلب تعديل أي بيانات غير دقيقة، أو سحب موافقتك على معالجة البيانات غير الضرورية في أي وقت.",
          ],
        },
        {
          id: "children",
          title: "٩. خصوصية الأطفال",
          content: [
            "خدماتنا ليست موجهة للأطفال دون سن ١٣ عاماً، ولا نجمع عمداً بيانات شخصية من أطفال دون موافقة أولياء أمورهم.",
          ],
        },
        {
          id: "changes",
          title: "١٠. التعديلات على سياسة الخصوصية",
          content: [
            "قد نقوم بتحديث سياسة الخصوصية من وقت لآخر لتعكس أي تغييرات في ممارساتنا التقنية أو التنظيمية. سننشر أي تحديثات على هذه الصفحة مع تحديث تاريخ المراجعة.",
          ],
        },
        {
          id: "contact",
          title: "١١. التواصل معنا",
          content: [
            "إذا كانت لديك أي أسئلة أو استفسارات حول سياسة الخصوصية، يمكنك التواصل معنا عبر صفحة الدعم والمساعدة أو عبر البريد الإلكتروني الرسمي للدعم.",
          ],
        },
      ],
    },
    footer: {
      copyright: "جميع الحقوق محفوظة لتطبيق FUN MOMENT.",
      allRightsReserved: "تطبيق الترفيه والتجارب الممتعة.",
    },
  },
  en: {
    nav: {
      home: "Home",
      privacy: "Privacy Policy",
      support: "Support & Help",
      getApp: "Get App",
      switchLang: "العربية",
      switchLangTarget: "ar",
    },
    hero: {
      kicker: "The Leisure & Experiences App",
      slides: [
        {
          tag: "Unforgettable Moments",
          title: "Make Every Moment Count.",
          subtitle: "Discover enjoyable experiences, explore leisure activities, and create memorable moments with the FUN MOMENT app.",
          accentColor: "cyan",
        },
        {
          tag: "Connect & Share",
          title: "Moments Worth Sharing.",
          subtitle: "Explore vibrant atmospheres, discover activities, and share joy with the people you care about effortlessly.",
          accentColor: "amber",
        },
        {
          tag: "Fresh Experiences",
          title: "Your Next Great Moment Starts Here.",
          subtitle: "Your modern digital companion to discover exciting events and craft unforgettable memories.",
          accentColor: "pink",
        },
      ],
      primaryCta: "Download App",
      secondaryCta: "Support & Help",
      availableOn: "Available for iOS & Android",
    },
    intro: {
      badge: "About The App",
      heading: "What is FUN MOMENT?",
      paragraph: "FUN MOMENT is a modern digital platform designed to help you discover events, leisure activities, and live experiences, creating memorable moments with friends and family in a seamless, engaging interface.",
      benefits: [
        {
          title: "Seamless Discovery",
          description: "Browse enjoyable activities and entertainment near you with speed and ease.",
          color: "cyan",
        },
        {
          title: "Modern Interactive Experience",
          description: "A refined user experience crafted to celebrate vibrant social lifestyle.",
          color: "amber",
        },
        {
          title: "Memorable Moments",
          description: "Plan your leisure time with confidence and savor every moment.",
          color: "pink",
        },
      ],
    },
    appCta: {
      title: "Download FUN MOMENT",
      subtitle: "Start your journey and enjoy memorable entertainment experiences.",
      appStore: "App Store",
      googlePlay: "Google Play",
    },
    support: {
      badge: "User Assistance",
      title: "Support & Help",
      subtitle: "The FUN MOMENT support team is here to assist you with any questions or inquiries.",
      contactTitle: "Contact Support",
      contactSubtitle: "Send us a message and our support team will get back to you as soon as possible.",
      form: {
        name: "Full Name",
        namePlaceholder: "Saud Al-Fahad",
        email: "Email Address",
        emailPlaceholder: "name@example.com",
        phone: "Phone Number (Optional)",
        phonePlaceholder: "+966 5X XXX XXXX",
        topic: "Topic",
        topics: {
          account: "Account & Login",
          technical: "App Technical Issues",
          experiences: "Experience / Content Inquiries",
          deletion: "Account & Data Deletion Request",
          general: "General Inquiry",
        },
        message: "Message",
        messagePlaceholder: "Describe your inquiry or question here...",
        submit: "Send Message",
        submitting: "Sending...",
        successTitle: "Message Received Successfully!",
        successMessage: "Thank you for reaching out to FUN MOMENT. We have received your request and will reply via email shortly.",
        errorRequired: "This field is required.",
        errorEmail: "Please enter a valid email address.",
        errorMessage: "Failed to deliver support email. Please try again or email our support desk directly.",
        errorNetwork: "Could not connect to the server. Please check your network and try again.",
      },
      topicsTitle: "Common Help Topics",
      topicsSubtitle: "General guidance to assist your app experience.",
      topicsList: [
        {
          title: "Account & Login Assistance",
          description: "Help with account setup, updating profile details, or login troubleshooting.",
        },
        {
          title: "App Technical Support",
          description: "Resolving performance issues, notifications setup, or app update questions.",
        },
        {
          title: "Browsing & Experience Inquiries",
          description: "Guidance on exploring featured experiences and activities within the app.",
        },
      ],
      deletionTitle: "Account & Personal Data Deletion",
      deletionSubtitle: "You have complete control over your account and personal data at all times.",
      deletionSteps: [
        "Open the FUN MOMENT app on your mobile device.",
        "Navigate to Profile, then select Settings.",
        "Tap on 'Account' and select 'Delete Account'.",
        "Confirm the deletion request to permanently schedule your account and associated personal data for deletion.",
      ],
      deletionWebNote: "If you cannot access your mobile device or encounter any issue completing the in-app steps, you can select 'Account & Data Deletion Request' in the form above or email our support address directly, and we will process your deletion request promptly.",
      emailLabel: "Direct Support Email:",
    },
    privacy: {
      badge: "Privacy & Transparency",
      title: "Privacy Policy",
      subtitle: "FUN MOMENT is committed to protecting your privacy and managing your data responsibly and transparently.",
      lastUpdated: "Last Updated:",
      tableOfContents: "Table of Contents",
      sections: [
        {
          id: "intro",
          title: "1. Introduction",
          content: [
            "This Privacy Policy describes how the FUN MOMENT application ('the App', 'we', 'our') collects, uses, and safeguards personal data when you use our services.",
            "By accessing or using the App, you acknowledge and agree to the data collection and usage practices described in this document.",
          ],
        },
        {
          id: "collection",
          title: "2. Information We Collect",
          content: [
            "We collect information you provide directly to us when using the App:",
            "• Account Information: Name, email address, and mobile phone number provided during account creation or profile updates.",
            "• Interaction Information: Information on activities and experiences you view or save within the App.",
            "• Technical & Device Information: Device type, operating system, and diagnostic identifiers to ensure app stability and performance.",
          ],
        },
        {
          id: "usage",
          title: "3. How We Use Information",
          content: [
            "We use collected information for the following legitimate purposes:",
            "• Providing, operating, and improving the App features.",
            "• Responding to support inquiries and customer assistance requests.",
            "• Dispatching vital service notices or security alerts.",
            "• Ensuring technical stability, debugging, and service optimization.",
          ],
        },
        {
          id: "sharing",
          title: "4. Data Sharing & Third Parties",
          content: [
            "We do not sell, rent, or trade your personal data to third parties.",
            "Information may only be shared with trusted technical infrastructure providers (such as cloud hosting and verification SMS gateways) strictly necessary to operate the service, or with legal authorities if required by applicable laws.",
            "Website Support Form Processing: Inquiries submitted through the website contact and support form are processed by Web3Forms as a technical service to relay messages to FUN MOMENT's support inbox (funmomentsproject@gmail.com). This processing is subject to the Web3Forms Privacy Policy: https://web3forms.com/privacy",
          ],
        },
        {
          id: "security",
          title: "5. Data Security",
          content: [
            "We apply technical and organizational safeguards designed to protect personal information against unauthorized access, loss, or alteration.",
          ],
        },
        {
          id: "retention",
          title: "6. Data Retention",
          content: [
            "We retain personal data for as long as your account remains active or as needed to provide you with the services and comply with applicable obligations.",
          ],
        },
        {
          id: "deletion",
          title: "7. Account & Data Deletion",
          content: [
            "You have the right to request the deletion of your account and all associated personal data at any time.",
            "You may delete your account directly inside the mobile app (Profile > Settings > Delete Account), or submit an account deletion request through the Support page on this website.",
            "Upon receiving a confirmed deletion request, your account data is permanently removed in accordance with technical procedures.",
          ],
        },
        {
          id: "rights",
          title: "8. User Rights",
          content: [
            "You have the right to access your stored personal data, request the correction of inaccurate records, or withdraw consent to non-essential data processing at any time.",
          ],
        },
        {
          id: "children",
          title: "9. Children's Privacy",
          content: [
            "Our services are not directed to children under 13 years of age, and we do not knowingly collect personal data from children without parental consent.",
          ],
        },
        {
          id: "changes",
          title: "10. Changes to This Privacy Policy",
          content: [
            "We may update this Privacy Policy periodically to reflect changes in our practices or applicable legal requirements. The updated version will be posted here with the revised effective date.",
          ],
        },
        {
          id: "contact",
          title: "11. Contact Us",
          content: [
            "If you have any questions regarding this Privacy Policy or your data, please contact our support team via our Support & Help page or official support email.",
          ],
        },
      ],
    },
    footer: {
      copyright: "All rights reserved. FUN MOMENT.",
      allRightsReserved: "The leisure & experiences mobile application.",
    },
  },
};
