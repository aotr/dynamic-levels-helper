<?php

namespace Aotr\DynamicLevelHelper\Interface\WhatsApp;

/**
 * Interface WhatsAppMessageInterface
 *
 * Represents a structured payload for WhatsApp messages.
 */
interface WhatsAppMessageInterface
{
    /**
     * Prepare the payload for sending the message.
     *
     * The payload array should have the following structure:
     * - 'apiver': string (e.g., '1.0')
     * - 'whatsapp': array [
     *      'ver' => string,
     *      'dlr' => array [
     *          'url' => string
     *      ],
     *      'messages' => array [
     *          [
     *              'coding' => string,
     *              'id' => string,
     *              'msgtype' => int,
     *              'type' => string,
     *              'contenttype' => string,
     *              'b_urlinfo' => string,
     *              'mediadata' => string,
     *              'text' => string,
     *              'templateinfo' => string,
     *              'addresses' => array [
     *                  [
     *                      'seq' => string,
     *                      'from' => string,
     *                      'to' => string,
     *                      'tag' => string
     *                  ]
     *              ]
     *          ]
     *      ]
     * ]
     *
     * @return array{
     *     apiver: string,
     *     whatsapp: array{
     *         ver: string,
     *         dlr: array{
     *             url: string
     *         },
     *         messages: array<int, array{
     *             coding: string,
     *             id: string,
     *             msgtype: int,
     *             type: string,
     *             contenttype: string,
     *             b_urlinfo: string,
     *             mediadata: string,
     *             text: string,
     *             templateinfo: string,
     *             addresses: array<int, array{
     *                 seq: string,
     *                 from: string,
     *                 to: string,
     *                 tag: string
     *             }>
     *         }>
     *     }
     * }
     */
    public function preparePayload(): array;
}
