(function () {
	if (typeof wrpdAdmin === 'undefined') {
		return;
	}

	var modal = null;
	var lastFocus = null;

	document.addEventListener('click', function (event) {
		var dot = event.target.closest('.wrpd-dot');
		if (!dot) {
			return;
		}
		event.preventDefault();
		event.stopPropagation();
		openModal(dot.getAttribute('data-order-id'), dot);
	});

	document.addEventListener('keydown', function (event) {
		if (event.key === 'Escape' && modal && !modal.hidden) {
			closeModal();
		}
	});

	function openModal(orderId, trigger) {
		ensureModal();
		lastFocus = trigger;
		setBody(statusNode(wrpdAdmin.i18n.loading));
		modal.hidden = false;
		modal.querySelector('.wrpd-modal__close').focus();
		loadDetails(orderId);
	}

	function closeModal() {
		if (!modal) {
			return;
		}
		modal.hidden = true;
		if (lastFocus && typeof lastFocus.focus === 'function') {
			lastFocus.focus();
		}
	}

	function ensureModal() {
		if (modal) {
			return;
		}

		modal = document.createElement('div');
		modal.className = 'wrpd-modal';
		modal.hidden = true;
		modal.setAttribute('role', 'dialog');
		modal.setAttribute('aria-modal', 'true');
		modal.setAttribute('aria-labelledby', 'wrpd-modal-title');

		var backdrop = document.createElement('div');
		backdrop.className = 'wrpd-modal__backdrop';
		backdrop.addEventListener('click', closeModal);

		var panel = document.createElement('div');
		panel.className = 'wrpd-modal__panel';

		var header = document.createElement('div');
		header.className = 'wrpd-modal__header';

		var title = document.createElement('h2');
		title.id = 'wrpd-modal-title';
		title.textContent = wrpdAdmin.i18n.titleOther;

		var close = document.createElement('button');
		close.type = 'button';
		close.className = 'wrpd-modal__close';
		close.setAttribute('aria-label', wrpdAdmin.i18n.close);
		close.textContent = '\u00d7';
		close.addEventListener('click', closeModal);

		var body = document.createElement('div');
		body.className = 'wrpd-modal__body';

		header.appendChild(title);
		header.appendChild(close);
		panel.appendChild(header);
		panel.appendChild(body);
		modal.appendChild(backdrop);
		modal.appendChild(panel);
		document.body.appendChild(modal);
	}

	function setTitle(level) {
		var title = modal.querySelector('#wrpd-modal-title');
		title.textContent = level === 'red' ? wrpdAdmin.i18n.titleSame : wrpdAdmin.i18n.titleOther;
	}

	function setBody(node) {
		var body = modal.querySelector('.wrpd-modal__body');
		body.textContent = '';
		body.appendChild(node);
	}

	function statusNode(message, className) {
		var p = document.createElement('p');
		p.className = className || 'wrpd-modal__status';
		p.textContent = message;
		return p;
	}

	function loadDetails(orderId) {
		var body = new URLSearchParams();
		body.set('action', 'wrpd_repeat_details');
		body.set('nonce', wrpdAdmin.nonce);
		body.set('order_id', orderId);

		fetch(wrpdAdmin.ajaxUrl, {
			method: 'POST',
			credentials: 'same-origin',
			headers: {
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8'
			},
			body: body.toString()
		})
			.then(function (response) {
				return response.json();
			})
			.then(function (payload) {
				if (!payload || !payload.success) {
					var message = payload && payload.data && payload.data.message ? payload.data.message : wrpdAdmin.i18n.error;
					setBody(statusNode(message, 'wrpd-modal__error'));
					return;
				}
				renderOrders(payload.data || {});
			})
			.catch(function () {
				setBody(statusNode(wrpdAdmin.i18n.error, 'wrpd-modal__error'));
			});
	}

	function renderOrders(data) {
		setTitle(data.level);
		var orders = Array.isArray(data.orders) ? data.orders : [];
		if (!orders.length) {
			setBody(statusNode(wrpdAdmin.i18n.empty, 'wrpd-modal__empty'));
			return;
		}

		var wrap = document.createElement('div');
		orders.forEach(function (order) {
			wrap.appendChild(renderOrder(order));
		});
		setBody(wrap);
	}

	function renderOrder(order) {
		var block = document.createElement('article');
		block.className = 'wrpd-order';

		var title = document.createElement('h3');
		title.className = 'wrpd-order__title';

		var number = document.createElement('a');
		number.className = 'wrpd-order__number';
		number.href = order.url || '#';
		number.textContent = wrpdAdmin.i18n.order + ' #' + (order.number || order.id || '');

		var copy = document.createElement('button');
		copy.type = 'button';
		copy.className = 'button button-small wrpd-copy';
		copy.textContent = wrpdAdmin.i18n.copy;
		copy.addEventListener('click', function () {
			copyOrderNumber(String(order.number || order.id || ''), copy);
		});

		title.appendChild(number);
		if (order.date) {
			var date = document.createElement('span');
			date.className = 'wrpd-order__date';
			date.textContent = order.date;
			title.appendChild(date);
		}
		title.appendChild(copy);

		block.appendChild(title);
		block.appendChild(sectionLabel(wrpdAdmin.i18n.customer));
		block.appendChild(renderCustomer(order.customer || {}));
		block.appendChild(sectionLabel(wrpdAdmin.i18n.products));
		block.appendChild(renderProducts(order.products || []));

		return block;
	}

	function sectionLabel(text) {
		var p = document.createElement('p');
		p.className = 'wrpd-section-label';
		p.textContent = text;
		return p;
	}

	function renderCustomer(customer) {
		var list = document.createElement('dl');
		list.className = 'wrpd-customer';
		addPair(list, wrpdAdmin.i18n.name, customer.name);
		addPair(list, wrpdAdmin.i18n.phone, customer.phone);
		addPair(list, wrpdAdmin.i18n.email, customer.email);
		addPair(list, wrpdAdmin.i18n.address, customer.address);
		return list;
	}

	function addPair(list, label, value) {
		var dt = document.createElement('dt');
		dt.textContent = label;
		var dd = document.createElement('dd');
		dd.textContent = value ? String(value) : '—';
		list.appendChild(dt);
		list.appendChild(dd);
	}

	function renderProducts(products) {
		var table = document.createElement('table');
		table.className = 'wrpd-products';

		var head = document.createElement('thead');
		var headRow = document.createElement('tr');
		[wrpdAdmin.i18n.products, wrpdAdmin.i18n.sku, wrpdAdmin.i18n.qty].forEach(function (label) {
			var th = document.createElement('th');
			th.textContent = label;
			headRow.appendChild(th);
		});
		head.appendChild(headRow);
		table.appendChild(head);

		var body = document.createElement('tbody');
		if (!products.length) {
			var emptyRow = document.createElement('tr');
			var emptyCell = document.createElement('td');
			emptyCell.colSpan = 3;
			emptyCell.textContent = '—';
			emptyRow.appendChild(emptyCell);
			body.appendChild(emptyRow);
		}

		products.forEach(function (product) {
			var row = document.createElement('tr');

			var nameCell = document.createElement('td');
			nameCell.textContent = product.name || '—';
			if (product.matched) {
				var badge = document.createElement('span');
				badge.className = 'wrpd-match';
				badge.textContent = wrpdAdmin.i18n.matched;
				nameCell.appendChild(badge);
			}

			var skuCell = document.createElement('td');
			skuCell.textContent = product.sku || '—';

			var qtyCell = document.createElement('td');
			qtyCell.textContent = String(product.qty || 0);

			row.appendChild(nameCell);
			row.appendChild(skuCell);
			row.appendChild(qtyCell);
			body.appendChild(row);
		});

		table.appendChild(body);
		return table;
	}

	function copyOrderNumber(number, button) {
		var done = function () {
			var original = button.textContent;
			button.textContent = wrpdAdmin.i18n.copied;
			window.setTimeout(function () {
				button.textContent = original;
			}, 1500);
		};

		if (navigator.clipboard && window.isSecureContext) {
			navigator.clipboard.writeText(number).then(done).catch(function () {
				fallbackCopy(number);
				done();
			});
			return;
		}

		fallbackCopy(number);
		done();
	}

	function fallbackCopy(number) {
		var input = document.createElement('textarea');
		input.value = number;
		input.setAttribute('readonly', '');
		input.style.position = 'fixed';
		input.style.left = '-9999px';
		document.body.appendChild(input);
		input.select();
		try {
			document.execCommand('copy');
		} catch (error) {
			input.remove();
			return;
		}
		input.remove();
	}
})();
