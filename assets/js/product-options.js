(() => {
  const form = document.querySelector('[data-options-form]');
  if (!form) return;

  const select = form.querySelector('[data-variant-select]');
  const quantity = form.querySelector('[data-quantity-input]');
  const note = form.querySelector('[data-stock-note]');
  const button = form.querySelector('[data-add-button]');
  const price = document.querySelector('#selected-price');
  const regularPrice = document.querySelector('#was-price');
  const discount = document.querySelector('#discount-percent');

  const formatPrice = (amount) => `KSh ${new Intl.NumberFormat('en-KE', {
    maximumFractionDigits: 2
  }).format(amount)}`;

  select.addEventListener('change', () => {
    const option = select.selectedOptions[0];
    const stock = Number(option.dataset.stock || 0);
    const selected = Boolean(select.value) && stock > 0;
    quantity.max = String(Math.max(1, stock));
    quantity.value = selected ? String(Math.min(Number(quantity.value), stock)) : '1';
    button.disabled = !selected;
    note.textContent = selected ? `${stock} available` : 'Select an option to see availability.';
    if (selected && price) {
      const amount = Number(option.dataset.price);
      const original = Number(option.dataset.regularPrice);
      price.textContent = formatPrice(amount);
      if (regularPrice && discount) {
        const isDiscounted = original > amount;
        regularPrice.hidden = !isDiscounted;
        discount.hidden = !isDiscounted;
        if (isDiscounted) {
          regularPrice.textContent = formatPrice(original);
          discount.textContent = `-${Math.round((1 - amount / original) * 100)}%`;
        }
      }
    }
  });
})();
