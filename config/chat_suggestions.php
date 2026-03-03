<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Chat Suggestions / Chips
    |--------------------------------------------------------------------------
    |
    | El backend decide qué chips mostrar.
    |
    | Convenciones:
    | - mode = public | admin (lo manda el frontend)
    | - En admin, el frontend también manda business_id seleccionado.
    | - Comandos internos reservados: __...__
    |   - Público (fallback): __CONTACTAR_SOPORTE__, __INTENTAR_OTRA_PREGUNTA__
    |   - Admin (acciones IA): __IA_...__
    |
    | Cada chip:
    | - id: identificador estable (string)
    | - label: texto UI
    | - message: texto que se envía al /chat (string)
    | - category: agrupación / analytics (opcional)
    | - children: ids de chips sugeridos como follow-ups (opcional)
    | - min_plan: plan mínimo requerido en admin (basic|pro|elite) (opcional)
    */

    'limits' => [
        'max_visible' => 5,
        'max_children_visible' => 4,
    ],

    'public' => [

        // Chips iniciales (NO incluyen soporte/contacto/legal para evitar desviar conversión)
        'initial' => [
            'public.pricing',
            'public.plan_basic',
            'public.register',
            'public.how_it_works',
            'public.terms',
        ],

        // Chips sugeridos por categoría dominante (backend puede usar esto si quiere)
        // Nota: soporte/contacto NO se incluyen aquí; se agregan solo en fallback.
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
                'public.verify_email',
                'public.forgot_password',
            ],

            'legal' => [
                'public.terms',
                'public.privacy',
                'public.content_policy',
                'public.refunds',
            ],

            'proyecto' => [
                'public.how_it_works',
                'public.who_we_are',
                'public.pricing',
                'public.register',
            ],

            'negocio' => [
                'public.how_it_works',
                'public.pricing',
                'public.register',
                'public.compare_plans',
            ],

            'faq' => [
                'public.pricing',
                'public.register',
                'public.how_it_works',
                'public.terms',
            ],
        ],

        // Catálogo maestro de chips (público)
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
                'children' => ['public.requirements', 'public.verify_email'],
            ],

            'public.requirements' => [
                'id' => 'public.requirements',
                'label' => '¿Qué necesito para registrarme?',
                'message' => '¿Qué información necesito tener lista para registrarme en Nenis?',
                'category' => 'registro',
                'children' => ['public.register'],
            ],

            'public.verify_email' => [
                'id' => 'public.verify_email',
                'label' => 'Verificar correo',
                'message' => '¿Por qué debo verificar mi correo y qué pasa si no me llega el correo de confirmación?',
                'category' => 'registro',
                'children' => ['public.forgot_password'],
            ],

            'public.forgot_password' => [
                'id' => 'public.forgot_password',
                'label' => 'Recuperar contraseña',
                'message' => 'Olvidé mi contraseña. ¿Cómo puedo recuperar el acceso a mi cuenta?',
                'category' => 'registro',
            ],

            'public.multi_business' => [
                'id' => 'public.multi_business',
                'label' => '¿Varios negocios?',
                'message' => '¿Puedo registrar más de un negocio con una cuenta? ¿Cómo funciona la suscripción por usuario?',
                'category' => 'registro',
            ],

            'public.how_it_works' => [
                'id' => 'public.how_it_works',
                'label' => '¿Cómo funciona Nenis?',
                'message' => 'Explícame qué es Nenis y cómo ayuda a emprendedores. Resumen breve.',
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
                'label' => 'Términos',
                'message' => '¿Dónde puedo leer los términos y condiciones de Nenis? Dame un resumen y el link oficial.',
                'category' => 'legal',
                'children' => ['public.privacy', 'public.content_policy'],
            ],

            'public.privacy' => [
                'id' => 'public.privacy',
                'label' => 'Privacidad',
                'message' => '¿Cuál es el aviso de privacidad de Nenis? Resumen y link oficial.',
                'category' => 'legal',
            ],

            'public.content_policy' => [
                'id' => 'public.content_policy',
                'label' => 'Contenido permitido',
                'message' => '¿Qué tipo de negocios o contenido no se permite en Nenis? Resumen y link.',
                'category' => 'legal',
            ],

            'public.refunds' => [
                'id' => 'public.refunds',
                'label' => 'Pagos y devoluciones',
                'message' => '¿Cómo funcionan pagos, cancelaciones y devoluciones en Nenis? Resumen y link oficial.',
                'category' => 'legal',
            ],

            // Chips de fallback (NO se muestran por defecto; el backend los agrega solo si no hay KB)
            'public.support' => [
                'id' => 'public.support',
                'label' => 'Contactar soporte',
                'message' => '__CONTACTAR_SOPORTE__',
                'category' => 'contacto',
            ],

            'public.try_again' => [
                'id' => 'public.try_again',
                'label' => 'Intentar otra pregunta',
                'message' => '__INTENTAR_OTRA_PREGUNTA__',
                'category' => 'faq',
            ],
        ],
    ],

    'admin' => [

        // Chips iniciales por plan (backend los usa con gating por min_plan)
        'initial_by_plan' => [
            'basic' => [
                'admin.business_fields',
                'admin.complete_profile',
                'admin.how_search_works',
                'admin.upgrade_pro',
            ],
            'pro' => [
                'admin.gen_keywords',
                'admin.improve_description',
                'admin.plan_benefits',
                'admin.upgrade_elite',
            ],
            'elite' => [
                'admin.gen_keywords',
                'admin.improve_description',
                'admin.plan_benefits',
                'admin.support',
            ],
        ],

        'by_category' => [
            'planes' => [
                'admin.plan_benefits',
                'admin.upgrade_pro',
                'admin.upgrade_elite',
            ],
            'negocio' => [
                'admin.gen_keywords',
                'admin.improve_description',
                'admin.suggest_category',
            ],
        ],

        'chips' => [

            'admin.business_fields' => [
                'id' => 'admin.business_fields',
                'label' => 'Campos del negocio',
                'message' => '¿Qué información o campos necesito para registrar mi negocio de forma correcta?',
                'category' => 'onboarding',
                'min_plan' => 'basic',
            ],

            'admin.complete_profile' => [
                'id' => 'admin.complete_profile',
                'label' => 'Completar perfil',
                'message' => '¿Qué me falta para completar mi perfil de negocio y que sea aprobado?',
                'category' => 'onboarding',
                'min_plan' => 'basic',
            ],

            // Acciones IA (deterministas, requieren business_id válido en backend)
            'admin.gen_keywords' => [
                'id' => 'admin.gen_keywords',
                'label' => 'Generar keywords',
                'message' => '__IA_GENERAR_KEYWORDS__',
                'category' => 'ai_action',
                'min_plan' => 'pro',
                'children' => ['admin.apply_keywords_help'],
            ],

            'admin.improve_description' => [
                'id' => 'admin.improve_description',
                'label' => 'Mejorar descripción',
                'message' => '__IA_MEJORAR_DESCRIPCION__',
                'category' => 'ai_action',
                'min_plan' => 'pro',
            ],

            'admin.how_search_works' => [
                'id' => 'admin.how_search_works',
                'label' => '¿Cómo funciona la búsqueda?',
                'message' => 'Explícame cómo funciona la búsqueda y la prioridad por plan dentro de Nenis.',
                'category' => 'faq',
                'min_plan' => 'basic',
            ],

            'admin.upgrade_pro' => [
                'id' => 'admin.upgrade_pro',
                'label' => 'Mejorar a Pro',
                'message' => '¿Qué beneficios obtengo si actualizo al plan Pro?',
                'category' => 'planes',
                'min_plan' => 'basic',
            ],

            'admin.upgrade_elite' => [
                'id' => 'admin.upgrade_elite',
                'label' => 'Mejorar a Elite',
                'message' => '¿Qué beneficios obtengo si actualizo al plan Elite?',
                'category' => 'planes',
                'min_plan' => 'pro',
            ],

            'admin.plan_benefits' => [
                'id' => 'admin.plan_benefits',
                'label' => 'Beneficios de mi plan',
                'message' => 'Explícame los beneficios del plan que tengo y qué puedo hacer con el asistente IA.',
                'category' => 'planes',
                'min_plan' => 'basic',
            ],

            /*'admin.suggest_category' => [
                'id' => 'admin.suggest_category',
                'label' => 'Sugerir categoría',
                'message' => '__IA_SUGERIR_CATEGORIA__',
                'category' => 'ai_action',
                'min_plan' => 'pro',
            ],*/

            /*'admin.seo_audit' => [
                'id' => 'admin.seo_audit',
                'label' => 'Auditoría SEO',
                'message' => '__IA_AUDITORIA_SEO__',
                'category' => 'ai_action',
                'min_plan' => 'elite',
            ],

            /*'admin.generate_items_bulk' => [
                'id' => 'admin.generate_items_bulk',
                'label' => 'Generar productos/servicios',
                'message' => '__IA_GENERAR_ITEMS__',
                'category' => 'ai_action',
                'min_plan' => 'elite',
            ],*/

            'admin.apply_keywords_help' => [
                'id' => 'admin.apply_keywords_help',
                'label' => '¿Cómo aplico las keywords?',
                'message' => '¿Cómo aplico o edito las keywords sugeridas antes de guardarlas?',
                'category' => 'ai_help',
                'min_plan' => 'pro',
            ],

            'admin.support' => [
                'id' => 'admin.support',
                'label' => 'Soporte',
                'message' => '__CONTACTAR_SOPORTE__',
                'category' => 'contacto',
                'min_plan' => 'basic',
            ],
        ],
    ],

];