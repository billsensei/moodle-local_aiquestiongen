// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Review page behaviour: edit, approve and reject draft questions without reloading.
 *
 * @module     local_aiquestiongen/review
 * @copyright  2026 Bill White
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

import {call as fetchMany} from 'core/ajax';
import Templates from 'core/templates';
import Notification from 'core/notification';
import {getString} from 'core/str';

const COMPONENT = 'local_aiquestiongen';

/**
 * Call one web service function.
 *
 * @param {String} methodname The function name.
 * @param {Object} args Its arguments.
 * @returns {Promise}
 */
const call = (methodname, args) => fetchMany([{methodname, args}])[0];

/**
 * Tell screen reader users what happened.
 *
 * @param {HTMLElement} root The review root.
 * @param {String} message The message.
 */
const announce = (root, message) => {
    const live = root.querySelector('[data-region="live"]');
    live.textContent = '';
    // A short delay makes repeated identical messages get announced again.
    window.setTimeout(() => {
        live.textContent = message;
    }, 50);
};

/**
 * Recount the cards and update the summary line.
 *
 * @param {HTMLElement} root The review root.
 */
const updateCounts = (root) => {
    const cards = root.querySelectorAll('[data-region="item"]');
    const counts = {pending: 0, approved: 0, rejected: 0, flagged: 0};
    cards.forEach((card) => {
        const status = card.dataset.status;
        if (status in counts) {
            counts[status]++;
        }
        if (card.dataset.flagged === '1' && status === 'pending') {
            counts.flagged++;
        }
    });
    Object.keys(counts).forEach((key) => {
        const el = root.querySelector(`[data-count="${key}"]`);
        if (el) {
            el.textContent = counts[key];
        }
    });
};

/**
 * Redraw one card from fresh data from the server.
 *
 * @param {HTMLElement} root The review root.
 * @param {Object} item The item as returned by the web service.
 * @param {Number} number Its position on the page.
 * @param {Boolean} editing Show the edit form.
 * @param {Boolean} regenerating Show the regenerate form.
 * @returns {Promise}
 */
const renderCard = (root, item, number, editing, regenerating = false) => {
    const card = root.querySelector(`[data-region="item"][data-itemid="${item.id}"]`);
    return Templates.renderForPromise('local_aiquestiongen/review_item', {...item, number, editing, regenerating})
        .then(({html, js}) => {
            Templates.replaceNode(card, html, js);
            updateCounts(root);
            return root.querySelector(`[data-region="item"][data-itemid="${item.id}"]`);
        });
};

/**
 * Read the edit form into web service arguments.
 *
 * @param {HTMLElement} card The card in edit mode.
 * @returns {Object} The stem and answers.
 */
const readForm = (card) => {
    const stem = card.querySelector('[data-field="stem"]').value;
    const field = (name) => card.querySelector(`[data-field="${name}"]`).value;

    if (card.dataset.qtype === 'truefalse') {
        const chosen = card.querySelector('[data-field="tfcorrect"]:checked');
        return {
            stem,
            answers: [],
            // Leave the answer as it was if the teacher cleared the choice (there is no way to, but be safe).
            correcttrue: chosen ? chosen.value === '1' : undefined,
            feedbacktrue: field('feedbacktrue'),
            feedbackfalse: field('feedbackfalse'),
        };
    }
    if (card.dataset.qtype === 'multianswer') {
        return {stem, answers: [], fillanswer: field('fillanswer'), alternatives: field('alternatives')};
    }
    if (card.dataset.qtype === 'gapselect') {
        return {stem, answers: [], fillanswer: field('fillanswer'), distractors: field('distractors')};
    }
    if (card.dataset.qtype === 'essay') {
        return {stem, answers: [], modelanswer: field('modelanswer'), rubric: field('rubric')};
    }

    const checked = card.querySelector('[data-field="correct"]:checked');
    const correctIndex = checked ? parseInt(checked.value, 10) : -1;
    return {
        stem,
        answers: Array.from(card.querySelectorAll('[data-region="answer"]')).map((row, index) => ({
            text: row.querySelector('[data-field="text"]').value,
            correct: index === correctIndex,
            feedback: row.querySelector('[data-field="feedback"]').value,
        })),
    };
};

/**
 * Initialise the review page.
 *
 * @param {Number} jobId The job id.
 */
export const init = (jobId) => {
    const root = document.querySelector('[data-region="review"]');
    if (!root) {
        return;
    }
    // Remember each card's data so Edit and Cancel can redraw it without asking the server. The server
    // puts the drafts in a data attribute.
    const items = new Map();
    const remember = (item) => items.set(item.id, item);
    JSON.parse(root.dataset.items || '[]').forEach(remember);

    root.addEventListener('click', (e) => {
        const button = e.target.closest('[data-action]');
        if (!button || !root.contains(button)) {
            return;
        }
        const card = button.closest('[data-region="item"]');
        handle(button.dataset.action, button, card).catch(Notification.exception);
    });

    const handle = async(action, button, card) => {
        if (action === 'approve-all' || action === 'reject-all') {
            return bulk(action, button.dataset.qtype);
        }
        const itemId = parseInt(card.dataset.itemid, 10);
        const number = parseInt(card.dataset.number, 10);

        switch (action) {
            case 'edit': {
                const fresh = await renderCard(root, items.get(itemId), number, true);
                fresh.querySelector('[data-field="stem"]').focus();
                break;
            }
            case 'cancel': {
                const fresh = await renderCard(root, items.get(itemId), number, false);
                fresh.querySelector('[data-action="edit"]').focus();
                break;
            }
            case 'save': {
                const item = await call('local_aiquestiongen_update_item', {itemid: itemId, ...readForm(card)});
                remember(item);
                const fresh = await renderCard(root, item, number, false);
                fresh.querySelector('[data-action="edit"]').focus();
                announce(root, await getString('live_saved', COMPONENT, number));
                break;
            }
            case 'regenerate': {
                const fresh = await renderCard(root, items.get(itemId), number, false, true);
                fresh.querySelector('[data-field="regencomment"]').focus();
                break;
            }
            case 'regenerate-go': {
                const comment = card.querySelector('[data-field="regencomment"]').value;
                card.querySelectorAll('button').forEach((b) => {
                    b.disabled = true;
                });
                card.setAttribute('aria-busy', 'true');
                announce(root, await getString('live_regenerating', COMPONENT, number));
                let item;
                try {
                    item = await call('local_aiquestiongen_regenerate_item', {itemid: itemId, comment});
                } catch (error) {
                    // Keep the draft as it was and let the teacher try again.
                    const again = await renderCard(root, items.get(itemId), number, false, false);
                    again.querySelector('[data-action="regenerate"]')?.focus();
                    throw error;
                }
                remember(item);
                const fresh = await renderCard(root, item, number, false);
                fresh.querySelector('[data-action]')?.focus();
                announce(root, await getString('live_regenerated', COMPONENT, number));
                break;
            }
            case 'approve':
            case 'reject':
            case 'undo': {
                const status = {approve: 'approved', reject: 'rejected', undo: 'pending'}[action];
                const item = await call('local_aiquestiongen_set_item_status', {itemid: itemId, status});
                remember(item);
                const fresh = await renderCard(root, item, number, false);
                const next = fresh.querySelector('[data-action]');
                if (next) {
                    next.focus();
                }
                announce(root, await getString('live_' + status, COMPONENT, number));
                break;
            }
        }
    };

    const bulk = async(action, qtype) => {
        const status = action === 'approve-all' ? 'approved' : 'rejected';
        if (status === 'rejected') {
            const pending = root.querySelectorAll(
                `[data-region="group"][data-qtype="${qtype}"] [data-region="item"][data-status="pending"]`
            ).length;
            if (pending === 0) {
                return;
            }
            const confirmed = await new Promise((resolve) => {
                Notification.saveCancelPromise(
                    getString('action_rejectall', COMPONENT),
                    getString('confirmrejectall', COMPONENT, pending),
                    getString('action_rejectall', COMPONENT)
                ).then(() => resolve(true)).catch(() => resolve(false));
            });
            if (!confirmed) {
                return;
            }
        }
        const result = await call('local_aiquestiongen_set_items_status', {jobid: jobId, qtype, status});
        for (const item of result.items) {
            remember(item);
            const card = root.querySelector(`[data-region="item"][data-itemid="${item.id}"]`);
            await renderCard(root, item, parseInt(card.dataset.number, 10), false);
        }
        announce(root, await getString('live_bulk', COMPONENT, {changed: result.items.length, skipped: result.skipped}));
    };
};
