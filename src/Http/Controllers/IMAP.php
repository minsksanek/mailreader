<?php

namespace Minsksanek\Mailreader;

/**
 * Константы
 * @package Minsksanek\Mailreader\Http\Controllers
 */
class IMAP
{
    /**
     * Message
     */
    const MESSAGE_TYPE_TEXT = 0;
    const MESSAGE_TYPE_MULTIPART = 1;
    const MESSAGE_ENC_7BIT = 0;
    const MESSAGE_ENC_8BIT = 1;
    const MESSAGE_ENC_BINARY = 2;
    const MESSAGE_ENC_BASE64 = 3;
    const MESSAGE_ENC_QUOTED_PRINTABLE = 4;
    const MESSAGE_ENC_OTHER = 5;

    /**
     * Attachment const
     */
    const ATTACHMENT_TYPE_TEXT = 0;
    const ATTACHMENT_TYPE_MULTIPART = 1;
    const ATTACHMENT_TYPE_MESSAGE = 2;
    const ATTACHMENT_TYPE_APPLICATION = 3;
    const ATTACHMENT_TYPE_AUDIO = 4;
    const ATTACHMENT_TYPE_IMAGE = 5;
    const ATTACHMENT_TYPE_VIDEO = 6;
    const ATTACHMENT_TYPE_MODEL = 7;
    const ATTACHMENT_TYPE_OTHER = 8;

    /**
     * Client const
     */
    const CLIENT_OPENTIMEOUT = 1;
    const CLIENT_READTIMEOUT = 2;
    const CLIENT_WRITETIMEOUT = 3;
    const CLIENT_CLOSETIMEOUT = 4;

    /**
     * Generic imap const
     */
    const NIL = 0;
    const IMAP_OPENTIMEOUT = 1;
    const IMAP_READTIMEOUT = 2;
    const IMAP_WRITETIMEOUT = 3;
    const IMAP_CLOSETIMEOUT = 4;
    const OP_DEBUG = 1;
    const OP_READONLY = 2;
    const OP_ANONYMOUS = 4;
    const OP_SHORTCACHE = 8;
    const OP_SILENT = 16;
    const OP_PROTOTYPE = 32;
    const OP_HALFOPEN = 64;
    const OP_EXPUNGE = 128;
    const OP_SECURE = 256;
    const CL_EXPUNGE = 32768;
    const FT_UID = 1;
    const FT_PEEK = 2;
    const FT_NOT = 4;
    const FT_INTERNAL = 8;
    const FT_PREFETCHTEXT = 32;
    const ST_UID = 1;
    const ST_SILENT = 2;
    const ST_SET = 4;
    const CP_UID = 1;
    const CP_MOVE = 2;
    const SE_UID = 1;
    const SE_FREE = 2;
    const SE_NOPREFETCH = 4;
    const SO_FREE = 8;
    const SO_NOSERVER = 16;
    const SA_MESSAGES = 1;
    const SA_RECENT = 2;
    const SA_UNSEEN = 4;
    const SA_UIDNEXT = 8;
    const SA_UIDVALIDITY = 16;
    const SA_ALL = 31;
    const LATT_NOINFERIORS = 1;         // Этот ящик не имеет и не может иметь потомков (содержать вложенные ящики). Вызов функции imap_createmailbox() не будет работать для этого ящика.
    const LATT_NOSELECT = 2;            // Это только контейнер, а не почтовый ящик. Вы не можете его открыть.
    const LATT_MARKED = 4;              // Этот ящик помечен. Означает, что в нем могут быть новые письма, появившиеся с момента последней проверки. Работает не со всеми серверами IMAP.
    const LATT_UNMARKED = 8;            // Этот ящик не помечен, т.е. в нем нет новых писем. Если один из флагов MARKED или UNMARKED выставлен - можете считать, что сервер поддержимвает этот функционал.
    const LATT_REFERRAL = 16;           // Этот контейнер имеет направления (referral) на удаленный почтовый ящик.
    const LATT_HASCHILDREN = 32;        // Этот почтовый ящик имеет выбираемые подчиненные (inferiors).
    const LATT_HASNOCHILDREN = 64;      // Этот почтовый ящик не имеет выбираемые подчиненные (inferiors).
    const SORTDATE = 0;
    const SORTARRIVAL = 1;
    const SORTFROM = 2;
    const SORTSUBJECT = 3;
    const SORTTO = 4;
    const SORTCC = 5;
    const SORTSIZE = 6;
    const TYPETEXT = 0;
    const TYPEMULTIPART = 1;
    const TYPEMESSAGE = 2;
    const TYPEAPPLICATION = 3;
    const TYPEAUDIO = 4;
    const TYPEIMAGE = 5;
    const TYPEVIDEO = 6;
    const TYPEMODEL = 7;
    const TYPEOTHER = 8;
    const ENC7BIT = 0;
    const ENC8BIT = 1;
    const ENCBINARY = 2;
    const ENCBASE64 = 3;
    const ENCQUOTEDPRINTABLE = 4;
    const ENCOTHER = 5;
    const IMAP_GC_ELT = 1;
    const IMAP_GC_ENV = 2;
    const IMAP_GC_TEXTS = 4;
}
