<?php

declare(strict_types=1);

/**
 * Derafu: File - Files, directories, paths, temporary files, archives and MIME types.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    // Path.
    'The path contains a null character.' =>
        'La ruta contiene un carácter nulo.',
    'The path does not exist: {path}' =>
        'La ruta no existe: {path}',
    'The path {path} is outside of {base}.' =>
        'La ruta {path} está fuera de {base}.',

    // File.
    'The file does not exist: {file}' =>
        'El archivo no existe: {file}',
    'The path is not a file: {file}' =>
        'La ruta no es un archivo: {file}',
    'Cannot read file: {file}' =>
        'No se puede leer el archivo: {file}',
    'Failed to read file: {file}' =>
        'No se pudo leer el archivo: {file}',
    'The directory is not writable: {directory}' =>
        'El directorio no es escribible: {directory}',
    'Unable to create temporary file in directory ({directory}).' =>
        'No se pudo crear el archivo temporal en el directorio ({directory}).',
    'Unable to write content to temporary file ({file}).' =>
        'No se pudo escribir el contenido en el archivo temporal ({file}).',
    'Unable to set permissions on temporary file ({file}).' =>
        'No se pudieron establecer los permisos del archivo temporal ({file}).',
    'Unable to move temporary file to target location ({file}).' =>
        'No se pudo mover el archivo temporal a su ubicación de destino ({file}).',
    'The path is a directory, not a file: {file}' =>
        'La ruta es un directorio, no un archivo: {file}',
    'Failed to delete file: {file}' =>
        'No se pudo eliminar el archivo: {file}',

    // Directory.
    'Unable to create directory ({directory}).' =>
        'No se pudo crear el directorio ({directory}).',
    'The directory does not exist: {directory}' =>
        'El directorio no existe: {directory}',
    'The path is not a directory: {directory}' =>
        'La ruta no es un directorio: {directory}',
    'Cannot read directory: {directory}' =>
        'No se puede leer el directorio: {directory}',
    'Failed to read directory {directory}: {error}' =>
        'No se pudo leer el directorio {directory}: {error}',
    'Failed to remove directory {directory}: {error}' =>
        'No se pudo eliminar el directorio {directory}: {error}',

    // TempFile.
    'The extension "{extension}" is not valid.' =>
        'La extensión "{extension}" no es válida.',
    'The prefix "{prefix}" is not valid.' =>
        'El prefijo "{prefix}" no es válido.',

    // Zip.
    'The name of a ZIP entry must not be empty.' =>
        'El nombre de una entrada ZIP no puede estar vacío.',
    'The name of the ZIP entry "{name}" is not valid.' =>
        'El nombre de la entrada ZIP "{name}" no es válido.',
    'The content of the ZIP entry "{name}" must be a string, {given} given.' =>
        'El contenido de la entrada ZIP "{name}" debe ser un texto, se entregó {given}.',
    'The source of the ZIP entry "{name}" must be a string, a stream, a resource or a file, {given} given.' =>
        'La fuente de la entrada ZIP "{name}" debe ser un texto, un stream, un recurso o un archivo, se entregó {given}.',
    'The memory for the ZIP archive must not be below zero, {given} given.' =>
        'La memoria para el archivo ZIP no puede ser menor que cero, se entregó {given}.',
    'Failed to create ZIP file: {error}' =>
        'No se pudo crear el archivo ZIP: {error}',
    'Cannot open destination file for writing: {destination}' =>
        'No se puede abrir el archivo de destino para escribir: {destination}',
    'ZIP extension is not available' =>
        'La extensión ZIP no está disponible',
    'Failed to open ZIP file: {file} (Error code: {code})' =>
        'No se pudo abrir el archivo ZIP: {file} (código de error: {code})',
    'File already exists: {file}' =>
        'El archivo ya existe: {file}',
    'Failed to extract ZIP file to: {destination}' =>
        'No se pudo extraer el archivo ZIP en: {destination}',
];
