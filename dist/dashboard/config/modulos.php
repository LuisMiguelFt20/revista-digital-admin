<?php
declare(strict_types=1);

function configuracionModulo(string $modulo): array
{
    $base = [
        'autores' => [
            'titulo'=>'Administración de autores','singular'=>'autor','icono'=>'✍️','tabla'=>'autores','activa'=>'autores',
            'descripcion'=>'Registra a las personas que firman los reportajes.',
            'campos'=>[
                'nombres'=>['Nombres','text',true], 'ap_paterno'=>['Apellido paterno','text',false],
                'ap_materno'=>['Apellido materno','text',false], 'nickname'=>['Seudónimo o nickname','text',false],
                'es_nickname'=>['Mostrar el seudónimo como nombre público','checkbox',false],
            ],
            'columnas'=>['nombres'=>'Nombres','ap_paterno'=>'Ap. paterno','ap_materno'=>'Ap. materno','nickname'=>'Nickname','es_nickname'=>'Usa nickname'],
        ],
        'reportajes' => [
            'titulo'=>'Administración de reportajes','singular'=>'reportaje','icono'=>'📰','tabla'=>'reportajes','activa'=>'reportajes',
            'descripcion'=>'Publica reportajes, asigna su autor y adjunta imágenes o PDF.',
            'campos'=>[
                'titulo'=>['Título','text',true], 'resumen_corto'=>['Resumen corto','textarea',false],
                'desarrollo'=>['Desarrollo','textarea',true],
                'foto_principal'=>['Foto principal','file-image',false,'reportajes'],
                'pdf_adjunto'=>['PDF adjunto','file-pdf',false,'reportajes_pdf'],
                'fecha_publicacion'=>['Fecha de publicación','date',true],
                'estado'=>['Estado editorial','select',true,['borrador'=>'Borrador','revision'=>'En revisión','publicado'=>'Publicado']],
                'es_destacado'=>['Reportaje destacado','checkbox',false],
                'autor_id'=>['Autor','foreign',true,'SELECT id, CONCAT_WS(" ", nombres, ap_paterno, ap_materno) etiqueta FROM autores ORDER BY nombres'],
                'usuario_id'=>['Usuario','session',true],
            ],
            'list_sql'=>'SELECT r.*, CONCAT_WS(" ",a.nombres,a.ap_paterno,a.ap_materno) autor FROM reportajes r JOIN autores a ON a.id=r.autor_id ORDER BY r.fecha_publicacion DESC,r.id DESC',
            'columnas'=>['titulo'=>'Título','autor'=>'Autor','fecha_publicacion'=>'Fecha','estado'=>'Estado','es_destacado'=>'Destacado','foto_principal'=>'Foto','pdf_adjunto'=>'PDF'],
        ],
        'reportajes_fotos' => [
            'titulo'=>'Fotos de reportajes','singular'=>'foto','icono'=>'🖼️','tabla'=>'reportajes_fotos','activa'=>'fotos',
            'descripcion'=>'Agrega y ordena imágenes adicionales de cada reportaje.',
            'campos'=>[
                'reportaje_id'=>['Reportaje','foreign',true,'SELECT id, titulo etiqueta FROM reportajes ORDER BY titulo'],
                'url_foto'=>['Fotografía','file-image',true,'reportajes_fotos'], 'orden'=>['Orden','number',false],
                'descripcion'=>['Descripción','text',false],
            ],
            'list_sql'=>'SELECT f.*,r.titulo reportaje FROM reportajes_fotos f JOIN reportajes r ON r.id=f.reportaje_id ORDER BY r.titulo,f.orden,f.id',
            'columnas'=>['reportaje'=>'Reportaje','url_foto'=>'Foto','orden'=>'Orden','descripcion'=>'Descripción'],
        ],
        'noticias' => [
            'titulo'=>'Administración de noticias','singular'=>'noticia','icono'=>'📣','tabla'=>'noticias','activa'=>'noticias',
            'descripcion'=>'Registra noticias breves y enlaces a fuentes externas.',
            'campos'=>[
                'titulo'=>['Título','text',true], 'foto'=>['Fotografía','file-image',false,'noticias'],
                'link_externo'=>['Enlace externo','url',false], 'fecha_publicacion'=>['Fecha de publicación','date',true],
                'estado'=>['Estado editorial','select',true,['borrador'=>'Borrador','revision'=>'En revisión','publicado'=>'Publicado']],
                'usuario_id'=>['Usuario','session',true],
            ],
            'columnas'=>['titulo'=>'Título','foto'=>'Foto','link_externo'=>'Enlace','fecha_publicacion'=>'Fecha','estado'=>'Estado'],
        ],
        'boletines' => [
            'titulo'=>'Administración de boletines','singular'=>'boletín','icono'=>'📄','tabla'=>'boletines','activa'=>'boletines',
            'descripcion'=>'Publica las ediciones descargables de la revista.',
            'campos'=>[
                'numero_boletin'=>['Número o código','text',true], 'resumen'=>['Resumen','textarea',false],
                'foto_portada'=>['Foto de portada','file-image',false,'boletines'],
                'archivo_pdf'=>['Archivo PDF','file-pdf',true,'boletines_pdf'],
                'fecha_publicacion'=>['Fecha de publicación','date',true],
                'estado'=>['Estado editorial','select',true,['borrador'=>'Borrador','revision'=>'En revisión','publicado'=>'Publicado']],
                'usuario_id'=>['Usuario','session',true],
            ],
            'columnas'=>['numero_boletin'=>'Número','resumen'=>'Resumen','foto_portada'=>'Portada','archivo_pdf'=>'PDF','fecha_publicacion'=>'Fecha','estado'=>'Estado'],
        ],
        'podcasts' => [
            'titulo'=>'Administración de pódcasts','singular'=>'pódcast','icono'=>'🎙️','tabla'=>'podcasts','activa'=>'podcasts',
            'descripcion'=>'Publica episodios mediante enlaces incrustables.',
            'campos'=>['titulo'=>['Título','text',true], 'url_embed'=>['URL del audio o embed','url',true], 'fecha_publicacion'=>['Fecha de publicación','date',true], 'estado'=>['Estado editorial','select',true,['borrador'=>'Borrador','revision'=>'En revisión','publicado'=>'Publicado']], 'usuario_id'=>['Usuario','session',true]],
            'columnas'=>['titulo'=>'Título','url_embed'=>'Enlace','fecha_publicacion'=>'Fecha','estado'=>'Estado'],
        ],
        'videos' => [
            'titulo'=>'Administración de videos','singular'=>'video','icono'=>'🎬','tabla'=>'videos','activa'=>'videos',
            'descripcion'=>'Publica videos mediante enlaces de YouTube, Vimeo u otra plataforma.',
            'campos'=>['titulo'=>['Título','text',true], 'url_embed'=>['URL del video o embed','url',true], 'fecha_publicacion'=>['Fecha de publicación','date',true], 'estado'=>['Estado editorial','select',true,['borrador'=>'Borrador','revision'=>'En revisión','publicado'=>'Publicado']], 'usuario_id'=>['Usuario','session',true]],
            'columnas'=>['titulo'=>'Título','url_embed'=>'Enlace','fecha_publicacion'=>'Fecha','estado'=>'Estado'],
        ],
    ];
    if (!isset($base[$modulo])) throw new RuntimeException('Módulo no válido.');
    return $base[$modulo];
}
