<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat Suggestions / Chips
    |--------------------------------------------------------------------------
    | Define suggested chips for public landing and admin panel.
    |
    | Usage idea:
    | - Show `public.initial` on first render (landing).
    | - After each response, detect dominant topic/category (based on KB articles used)
    |   and show `public.by_category[$category]` (max N).
    | - On admin panel, use `admin.by_plan[$plan]` + optionally `admin.by_category[$category]`.
    |
    | Each chip:
    | - id: stable identifier (string)
    | - label: UI text
    | - message: what you actually send to your /chat endpoint
    | - category: optional (used for grouping / analytics)
    | - children: optional array of chip ids (for follow-ups)
    | - requires_plan: optional (basic|pro|elite) for admin-only chips
    */

    'limits' => [
        'max_visible' => 5,          // maximum chips shown at once
        'max_children_visible' => 4, // when a chip is selected, how many children you may show
    ],

    'public' => [

        // First-time chips shown on landing
        'initial' => [
            'public.pricing',
            'public.register',
            'public.how_it_works',
            'public.contact',
            'public.terms',
        ],

        // Chips grouped by KB category (dominant topic after answering)
        'by_category' => [

            'planes' => [
                'public.pricing',
                'public.plan_basic',
                'public.plan_pro',
                'public.plan_elite',
                'public.compare_plans',
            ],

            'registro' => [
                'public.register',
                'public.requirements',
                'public.multi_business',
                'public.support',
                'public.contact',
            ],

            'legal' => [
                'public.terms',
                'public.privacy',
                'public.content_policy',
                'public.refunds',
                'public.contact',
            ],

            'contacto' => [
                'public.contact',
                'public.support',
                'public.register',
                'public.how_it_works',
                'public.pricing',
            ],

            'proyecto' => [
                'public.how_it_works',
                'public.who_we_are',
                'public.pricing',
                'public.register',
                'public.contact',
            ],

            'faq' => [
                'public.pricing',
                'public.register',
                'public.how_it_works',
                'public.contact',
                'public.support',
            ],
        ],

        // Master chip catalog (public)
        'chips' => [

            'public.pricing' => [
                'id' => 'public.pricing',
                'label' => 'Planes y precios',
                'message' => '¿Qué planes tiene Nenis y cuánto cuestan? Dame un resumen claro.',
                'category' => 'planes',
                'children' => [
                    'public.plan_basic',
                    'public.plan_pro',
                    'public.plan_elite',
                    'public.compare_plans',
                ],
            ],

            'public.plan_basic' => [
                'id' => 'public.plan_basic',
                'label' => '¿Es gratis?',
                'message' => '¿Existe un plan gratis en Nenis y qué incluye el plan Basic?',
                'category' => 'planes',
                'children' => ['public.register'],
            ],

            'public.plan_pro' => [
                'id' => 'public.plan_pro',
                'label' => 'Plan Pro',
                'message' => '¿Qué incluye el plan Pro y cuánto cuesta? ¿Qué beneficios tiene frente al Basic?',
                'category' => 'planes',
                'children' => ['public.compare_plans', 'public.register'],
            ],

            'public.plan_elite' => [
                'id' => 'public.plan_elite',
                'label' => 'Plan Elite',
                'message' => '¿Qué incluye el plan Elite y cuánto cuesta? ¿Qué beneficios tiene frente al Pro?',
                'category' => 'planes',
                'children' => ['public.compare_plans', 'public.register'],
            ],

            'public.compare_plans' => [
                'id' => 'public.compare_plans',
                'label' => 'Comparar planes',
                'message' => 'Compárame Basic vs Pro vs Elite en una lista corta de diferencias.',
                'category' => 'planes',
                'children' => ['public.register'],
            ],

            'public.register' => [
                'id' => 'public.register',
                'label' => '¿Cómo me registro?',
                'message' => '¿Cómo me registro en Nenis? Dame los pasos concretos.',
                'category' => 'registro',
                'children' => ['public.requirements', 'public.multi_business'],
            ],

            'public.requirements' => [
                'id' => 'public.requirements',
                'label' => '¿Qué necesito para registrarme?',
                'message' => '¿Qué información necesito tener lista para registrar mi negocio en Nenis?',
                'category' => 'registro',
                'children' => ['public.register'],
            ],

            'public.multi_business' => [
                'id' => 'public.multi_business',
                'label' => '¿Puedo registrar varios negocios?',
                'message' => '¿Puedo registrar más de un negocio con una cuenta? ¿Cómo funciona la suscripción por usuario?',
                'category' => 'registro',
            ],

            'public.how_it_works' => [
                'id' => 'public.how_it_works',
                'label' => '¿Cómo funciona Nenis?',
                'message' => 'Explícame qué es Nenis y cómo ayuda a los emprendedores. Resumen breve.',
                'category' => 'proyecto',
                'children' => ['public.pricing', 'public.register'],
            ],

            'public.who_we_are' => [
                'id' => 'public.who_we_are',
                'label' => '¿Quiénes somos?',
                'message' => '¿Quién está detrás de Nenis y cuál es la misión del proyecto?',
                'category' => 'proyecto',
            ],

            'public.terms' => [
                'id' => 'public.terms',
                'label' => 'Términos y condiciones',
                'message' => '¿Dónde puedo leer los términos y condiciones de Nenis? Dame un resumen y el link oficial.',
                'category' => 'legal',
                'children' => ['public.privacy', 'public.content_policy'],
            ],

            'public.privacy' => [
                'id' => 'public.privacy',
                'label' => 'Privacidad',
                'message' => '¿Cuál es la política de privacidad de Nenis? Resumen y link oficial.',
                'category' => 'legal',
            ],

            'public.content_policy' => [
                'id' => 'public.content_policy',
                'label' => 'Políticas de contenido',
                'message' => '¿Qué tipo de negocios o contenido no se permite en Nenis? Resumen y link.',
                'category' => 'legal',
            ],

            'public.refunds' => [
                'id' => 'public.refunds',
                'label' => 'Pagos y reembolsos',
                'message' => '¿Cómo funcionan los pagos, cancelaciones y reembolsos en Nenis? Resumen y link oficial.',
                'category' => 'legal',
            ],

            'public.contact' => [
                'id' => 'public.contact',
                'label' => 'Contacto',
                'message' => '¿Cómo puedo contactar a Nenis para soporte o dudas? Dame los canales y horarios si existen.',
                'category' => 'contacto',
                'children' => ['public.support'],
            ],

            'public.support' => [
                'id' => 'public.support',
                'label' => 'Soporte',
                'message' => 'Tengo una duda y necesito ayuda: ¿cuál es el canal de soporte y tiempos de respuesta?',
                'category' => 'contacto',
            ],
        ],
    ],

    'admin' => [

        // Chips shown on first render inside admin chat (depending on plan)
        'initial_by_plan' => [
            'basic' => [
                'admin.complete_profile',
                'admin.how_search_works',
                'admin.upgrade_pro',
                'admin.support',
            ],
            'pro' => [
                'admin.gen_keywords',
                'admin.improve_description',
                'admin.suggest_category',
                'admin.support',
            ],
            'elite' => [
                'admin.gen_keywords',
                'admin.improve_description',
                'admin.suggest_category',
                'admin.seo_audit',
                'admin.generate_items_bulk',
            ],
        ],

        // Optional: admin chips by topic category (based on KB or detected intent)
        'by_category' => [
            'planes' => [
                'admin.upgrade_pro',
                'admin.upgrade_elite',
                'admin.plan_benefits',
            ],
            'registro' => [
                'admin.complete_profile',
                'admin.support',
            ],
        ],

        'chips' => [

            'admin.complete_profile' => [
                'id' => 'admin.complete_profile',
                'label' => 'Completar mi perfil',
                'message' => '¿Qué me falta para completar mi perfil y publicar mi negocio correctamente?',
                'category' => 'onboarding',
                'requires_plan' => 'basic',
            ],

            'admin.how_search_works' => [
                'id' => 'admin.how_search_works',
                'label' => '¿Cómo funciona la búsqueda?',
                'message' => 'Explícame cómo funciona la búsqueda y la prioridad por plan dentro de Nenis.',
                'category' => 'faq',
                'requires_plan' => 'basic',
            ],

            'admin.support' => [
                'id' => 'admin.support',
                'label' => 'Soporte',
                'message' => 'Necesito ayuda con mi cuenta o negocio. ¿Cómo contacto soporte?',
                'category' => 'contacto',
                'requires_plan' => 'basic',
            ],

            'admin.upgrade_pro' => [
                'id' => 'admin.upgrade_pro',
                'label' => 'Mejorar a Pro',
                'message' => '¿Qué beneficios obtengo si actualizo al plan Pro?',
                'category' => 'planes',
                'requires_plan' => 'basic',
            ],

            'admin.upgrade_elite' => [
                'id' => 'admin.upgrade_elite',
                'label' => 'Mejorar a Elite',
                'message' => '¿Qué beneficios obtengo si actualizo al plan Elite?',
                'category' => 'planes',
                'requires_plan' => 'pro',
            ],

            'admin.plan_benefits' => [
                'id' => 'admin.plan_benefits',
                'label' => 'Beneficios de mi plan',
                'message' => 'Explícame los beneficios del plan que tengo y qué puedo hacer con el asistente IA.',
                'category' => 'planes',
                'requires_plan' => 'basic',
            ],

            // Pro+ actions (these will create ai_runs, not just chat)
            'admin.gen_keywords' => [
                'id' => 'admin.gen_keywords',
                'label' => 'Generar keywords',
                'message' => 'Quiero generar keywords para mi negocio. Guíame y propón una lista.',
                'category' => 'ai_action',
                'requires_plan' => 'pro',
                'children' => ['admin.apply_keywords_help'],
            ],

            'admin.improve_description' => [
                'id' => 'admin.improve_description',
                'label' => 'Mejorar descripción',
                'message' => 'Quiero mejorar la descripción de mi negocio para SEO local. Genera 1 propuesta.',
                'category' => 'ai_action',
                'requires_plan' => 'pro',
            ],

            'admin.suggest_category' => [
                'id' => 'admin.suggest_category',
                'label' => 'Sugerir categoría',
                'message' => 'Sugiere la mejor categoría para mi negocio según lo que vendo.',
                'category' => 'ai_action',
                'requires_plan' => 'pro',
            ],

            'admin.seo_audit' => [
                'id' => 'admin.seo_audit',
                'label' => 'Auditoría SEO',
                'message' => 'Quiero una auditoría SEO avanzada de mi ficha. Dime errores y mejoras.',
                'category' => 'ai_action',
                'requires_plan' => 'elite',
            ],

            'admin.generate_items_bulk' => [
                'id' => 'admin.generate_items_bulk',
                'label' => 'Generar productos/servicios',
                'message' => 'Quiero generar automáticamente productos/servicios (items) para mi negocio con propuestas.',
                'category' => 'ai_action',
                'requires_plan' => 'elite',
            ],

            'admin.apply_keywords_help' => [
                'id' => 'admin.apply_keywords_help',
                'label' => '¿Cómo aplico las keywords?',
                'message' => '¿Cómo aplico o edito las keywords sugeridas antes de guardarlas?',
                'category' => 'ai_help',
                'requires_plan' => 'pro',
            ],
        ],
    ],

];