document.addEventListener('DOMContentLoaded', function () {
  setupStartScreen();
  setupLocationSearch();
  setupAllergySelect();
  setupAgeRangeButtons();
  setupMedicationField();
  setupNoneCheckboxes();
  setupOtherRevealFields();
  setupPregnancyField();
  setupSeveritySlider();
  setupBodySelector();
  setupBodyModelMaterial();
  setupDescribeValidation();
  setupScreen3RedFlags();
  setupFollowupAlert();
  setupToggleSections();
  setupResultActions();
  setupFeedback();
  setupViewerSizeLabel();
});

function setupViewerSizeLabel() {
  var label = document.getElementById('viewerSizeLabel');
  var wrap  = label && label.closest('.describe-body-image-wrap');
  if (!label || !wrap) return;
  function update() {
    label.textContent = wrap.offsetWidth + ' × ' + wrap.offsetHeight + ' px';
  }
  update();
  window.addEventListener('resize', update);
  if (window.ResizeObserver) {
    new ResizeObserver(update).observe(wrap);
  }
}

function setupLocationSearch() {
  var wrap = document.querySelector('[data-location-search]');
  if (!wrap) return;

  var input = wrap.querySelector('[data-location-input]');
  var country = wrap.querySelector('[data-location-country]');
  var button = wrap.querySelector('[data-location-search-button]');
  var results = wrap.querySelector('[data-location-results]');
  var status = wrap.querySelector('[data-location-status]');
  var controller = null;
  var searchTimer = null;
  var lastQuery = '';

  function setStatus(message) {
    if (status) status.textContent = message || '';
  }

  function clearResults() {
    if (results) {
      results.innerHTML = '';
      results.classList.add('hidden');
    }
  }

  function placeLabel(place) {
    var address = place.address || {};
    var parts = [
      address.city || address.town || address.village || address.hamlet || address.suburb || address.county,
      address.state,
      address.country
    ].filter(Boolean);
    return parts.length ? parts.join(', ') : place.display_name;
  }

  async function searchLocations() {
    var query = input ? input.value.trim() : '';
    if (query.length < 3) {
      clearResults();
      setStatus(query.length ? 'Type at least 3 characters.' : '');
      return;
    }

    if (query === lastQuery && results && !results.classList.contains('hidden')) {
      return;
    }
    lastQuery = query;

    if (controller) controller.abort();
    controller = new AbortController();
    clearResults();
    setStatus('Searching locations...');

    try {
      var url = 'https://nominatim.openstreetmap.org/search?format=jsonv2&addressdetails=1&limit=6&q=' + encodeURIComponent(query);
      var response = await fetch(url, { signal: controller.signal, headers: { 'Accept': 'application/json' } });
      if (!response.ok) throw new Error('location_search_failed');
      var places = await response.json();
      if (!Array.isArray(places) || !places.length) {
        setStatus('No locations found. You can still type your location manually.');
        return;
      }

      results.innerHTML = '';
      places.forEach(function (place) {
        var option = document.createElement('button');
        option.type = 'button';
        option.textContent = placeLabel(place);
        option.addEventListener('click', function () {
          input.value = option.textContent;
          if (country) country.value = String((place.address && place.address.country_code) || '').toUpperCase();
          clearResults();
          setStatus('Location selected.');
        });
        results.appendChild(option);
      });
      results.classList.remove('hidden');
      setStatus('Choose the closest match.');
    } catch (error) {
      if (error.name === 'AbortError') return;
      setStatus('Location search is unavailable. You can still type your location manually.');
    }
  }

  function scheduleSearch() {
    window.clearTimeout(searchTimer);
    var query = input ? input.value.trim() : '';
    if (country) country.value = '';
    clearResults();
    if (query.length < 3) {
      setStatus(query.length ? 'Type at least 3 characters.' : '');
      return;
    }
    setStatus('Searching shortly...');
    searchTimer = window.setTimeout(searchLocations, 650);
  }

  if (button) button.addEventListener('click', searchLocations);
  if (input) {
    input.addEventListener('keydown', function (event) {
      if (event.key === 'Enter') {
        event.preventDefault();
        searchLocations();
      }
    });
    input.addEventListener('input', scheduleSearch);
  }
}

function setupAllergySelect() {
  var allergySelect = document.getElementById('allergySelect');
  var allergyOtherField = document.getElementById('allergyOtherField');
  if (!allergySelect || !allergyOtherField) return;

  function update() {
    var isOther = allergySelect.value === 'Other';
    allergyOtherField.classList.toggle('hidden', !isOther);
    if (!isOther) allergyOtherField.value = '';
  }

  allergySelect.addEventListener('change', update);
  update();
}

function setupStartScreen() {
  var splash = document.querySelector('[data-splash-screen]');
  var profile = document.querySelector('[data-profile-screen]');
  if (!splash || !profile) return;

  function showProfile() {
    splash.classList.add('hidden');
    profile.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  function showSplash() {
    profile.classList.add('hidden');
    splash.classList.remove('hidden');
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  document.querySelectorAll('[data-start-health-check]').forEach(function (button) {
    button.addEventListener('click', showProfile);
  });

  document.querySelectorAll('[data-back-to-splash]').forEach(function (button) {
    button.addEventListener('click', showSplash);
  });

  if (window.location.hash === '#profile') {
    showProfile();
  }
}

function setupMedicationField() {
  var input = document.querySelector('[data-medication-input]');
  var status = document.querySelector('[data-medication-status]');
  var clear = document.querySelector('[data-clear-medication]');
  var details = document.querySelector('[data-medication-details]');
  if (!input || !status) return;

  function update() {
    var hasMedication = Boolean(input.value.trim());
    status.value = hasMedication ? 'Yes' : 'No';
    if (details) {
      details.classList.toggle('hidden', !hasMedication);
      if (!hasMedication) {
        details.querySelectorAll('input, select').forEach(function (field) {
          if (field.type === 'radio' || field.type === 'checkbox') {
            field.checked = false;
          } else {
            field.value = '';
          }
        });
      }
    }
  }

  input.addEventListener('input', update);
  if (clear) {
    clear.addEventListener('click', function () {
      input.value = '';
      input.focus();
      update();
    });
  }
  update();
}

function setupAgeRangeButtons() {
  document.querySelectorAll('[data-age-fill]').forEach(function (button) {
    button.addEventListener('click', function () {
      var input = document.querySelector('input[name="age"]');
      if (!input) return;

      input.value = button.getAttribute('data-age-fill') || '';
      input.focus();
      input.dispatchEvent(new Event('input', { bubbles: true }));
      document.querySelectorAll('[data-age-fill]').forEach(function (item) {
        item.classList.toggle('is-selected', item === button);
      });
    });
  });
}

function setupOtherRevealFields() {
  document.querySelectorAll('[data-reveals-other]').forEach(function (checkbox) {
    var id = checkbox.getAttribute('data-reveals-other');
    var field = id ? document.getElementById(id) : null;
    if (!field) return;

    function update() {
      field.classList.toggle('hidden', !checkbox.checked);
      if (!checkbox.checked) {
        field.value = '';
      }
    }

    checkbox.addEventListener('change', update);
    var group = checkbox.closest('[data-checkbox-group]');
    if (group) {
      group.querySelectorAll('input[type="checkbox"]').forEach(function (box) {
        if (box !== checkbox) box.addEventListener('change', update);
      });
    }
    update();
  });
}

function setupPregnancyField() {
  var field = document.querySelector('[data-pregnancy-field]');
  if (!field) return;

  var sexInputs = Array.from(document.querySelectorAll('input[name="sex"]'));
  var ageInput = document.querySelector('input[name="age"]');
  var options = Array.from(field.querySelectorAll('[data-pregnancy-option]'));

  function selectedSex() {
    var selected = sexInputs.find(function (input) { return input.checked; });
    return selected ? selected.value : '';
  }

  function update() {
    var age = ageInput ? parseInt(ageInput.value, 10) : NaN;
    var show = selectedSex() === 'Female' && Number.isFinite(age) && age >= 12 && age <= 55;
    field.classList.toggle('hidden', !show);
    options.forEach(function (option) {
      option.required = show;
      if (!show) option.checked = false;
    });
  }

  sexInputs.forEach(function (input) { input.addEventListener('change', update); });
  if (ageInput) ageInput.addEventListener('input', update);
  update();
}

function setupSeveritySlider() {
  var slider = document.querySelector('[data-severity-slider]');
  var value = document.querySelector('[data-severity-value]');
  if (!slider || !value) return;

  function update() {
    value.textContent = slider.value;
    var percent = (Number(slider.value) / 10) * 100;
    slider.style.setProperty('--severity-fill', percent + '%');
    slider.style.background = 'linear-gradient(90deg, #356BEA 0 ' + percent + '%, #E8EEF7 ' + percent + '% 100%)';
  }

  slider.addEventListener('input', update);
  slider.addEventListener('change', update);
  update();
}

function setupNoneCheckboxes() {
  document.querySelectorAll('[data-checkbox-group]').forEach(function (group) {
    var none = group.querySelector('[data-none-checkbox]');
    if (!none) return;

    var boxes = Array.from(group.querySelectorAll('input[type="checkbox"]'));
    boxes.forEach(function (box) {
      box.addEventListener('change', function () {
        if (box === none && box.checked) {
          boxes.forEach(function (other) {
            if (other !== none) other.checked = false;
          });
        }

        if (box !== none && box.checked) {
          none.checked = false;
        }

        if (!boxes.some(function (item) { return item.checked; })) {
          none.checked = true;
        }
      });
    });
  });
}

function setupBodySelector() {
  var selector = document.querySelector('[data-body-selector]');
  if (!selector) return;

  var input = document.getElementById('bodyAreaInput');
  var text = document.getElementById('selectedAreaText');
  var viewer = document.getElementById('bodyViewer');
  var toggles = Array.from(document.querySelectorAll('[data-view-toggle]'));
  var panels = Array.from(document.querySelectorAll('[data-view-panel]'));
  var zones = Array.from(document.querySelectorAll('[data-body-area]'));

  if (viewer) {
    var camLabel = document.getElementById('viewerCamLabel');
    viewer.addEventListener('camera-change', function () {
      var orbit  = viewer.getCameraOrbit  ? viewer.getCameraOrbit()  : null;
      var fov    = viewer.getFieldOfView  ? viewer.getFieldOfView()  : null;
      var target = viewer.getCameraTarget ? viewer.getCameraTarget() : null;
      var orbitStr  = orbit  ? orbit.toString()  : viewer.getAttribute('camera-orbit');
      var fovStr    = fov    ? fov.toFixed(1) + 'deg' : (viewer.getAttribute('field-of-view') || 'auto');
      var targetStr = target ? (target.x.toFixed(3) + 'm ' + target.y.toFixed(3) + 'm ' + target.z.toFixed(3) + 'm') : viewer.getAttribute('camera-target');
      console.log('[camera-change]', { orbit: orbitStr, fov: fovStr, target: targetStr });
      if (camLabel) {
        camLabel.textContent = 'orbit:  ' + orbitStr + '\nfov:    ' + fovStr + '\ntarget: ' + targetStr;
      }
    });
  }

  function setView(view) {
    panels.forEach(function (panel) {
      panel.classList.toggle('hidden', panel.getAttribute('data-view-panel') !== view);
    });
    toggles.forEach(function (toggle) {
      toggle.dataset.active = toggle.getAttribute('data-view-toggle') === view ? 'true' : 'false';
    });
    if (viewer) viewer.setAttribute('camera-orbit', view === 'back' ? '180deg 75deg 489m' : '0deg 75deg 489m');
  }

  var areaZoom = {
    'Head':        { target: '0m 168m 11m',   fov: 4 },
    'Neck':        { target: '0m 153m 12m',   fov: 16 },
    'Chest':       { target: '0m 133m 12m',   fov: 18 },
    'Stomach':     { target: '0m 107m 12m',   fov: 18 },
    'Arm':         { target: '0m 122m 0m',    fov: 20 },
    'Leg':         { target: '0m 56m 8m',     fov: 20 },
    'Upper back':  { target: '0m 138m -12m',  fov: 18 },
    'Lower back':  { target: '0m 102m -12m',  fov: 18 },
  };

  var REF_HEIGHT = 480;
  var DEFAULT_BODY_FOV = 50;
  var DEFAULT_VISIBLE_H = 620;

  function scaledFov(baseFov) {
    var h = viewer ? (viewer.clientHeight || REF_HEIGHT) : REF_HEIGHT;
    var scaled = baseFov * (REF_HEIGHT / h);
    return Math.min(42, Math.max(8, Math.round(scaled * 10) / 10)) + 'deg';
  }

  function defaultFov() {
    var wrap = viewer ? viewer.closest('.describe-body-image-wrap') : null;
    var h = wrap ? (wrap.clientHeight || DEFAULT_VISIBLE_H) : DEFAULT_VISIBLE_H;
    var fov = DEFAULT_BODY_FOV * (DEFAULT_VISIBLE_H / h);
    return Math.min(42, Math.max(8, Math.round(fov * 10) / 10)) + 'deg';
  }

  var activeArea = '';

  function applyZoom(area) {
    if (!viewer) return;
    var zoom = area ? areaZoom[area] : null;
    if (zoom) {
      viewer.setAttribute('camera-target', zoom.target);
      viewer.setAttribute('field-of-view', scaledFov(zoom.fov));
    } else {
      viewer.setAttribute('camera-target', '0m 170m 3m');
      viewer.setAttribute('field-of-view', defaultFov());
    }
  }

  function setArea(area) {
    activeArea = area;
    input.value = area;
    input.dispatchEvent(new Event('change', { bubbles: true }));
    if (text) {
      text.textContent = 'Selected area: ' + (area || 'None');
    }
    zones.forEach(function (zone) {
      zone.classList.toggle('is-selected', zone.getAttribute('data-body-area') === area);
    });
    applyZoom(area);
  }

  if (viewer && window.ResizeObserver) {
    var wrapForRO = viewer.closest('.describe-body-image-wrap');
    var ro = new ResizeObserver(function () {
      applyZoom(activeArea);
    });
    ro.observe(viewer);
    if (wrapForRO) ro.observe(wrapForRO);
  }

  toggles.forEach(function (toggle) {
    toggle.addEventListener('click', function () {
      setView(toggle.getAttribute('data-view-toggle'));
    });
  });

  zones.forEach(function (zone) {
    zone.addEventListener('click', function () {
      setArea(zone.getAttribute('data-body-area'));
    });
  });

  var initial = selector.getAttribute('data-initial-area') || '';
  if (initial.toLowerCase().indexOf('back') !== -1) {
    setView('back');
  }
  setArea(initial);
}

function setupBodyModelMaterial() {
  var viewer = document.getElementById('bodyViewer');
  if (!viewer) return;

  function applyMaterialColor() {
    if (!viewer.model || !viewer.model.materials) return;

    viewer.model.materials.forEach(function (material) {
      var pbr = material.pbrMetallicRoughness;
      if (!pbr) return;

      if (pbr.setBaseColorFactor) {
        pbr.setBaseColorFactor([0.66, 0.74, 0.84, 1]);
      }
      if (pbr.setMetallicFactor) {
        pbr.setMetallicFactor(0.02);
      }
      if (pbr.setRoughnessFactor) {
        pbr.setRoughnessFactor(0.38);
      }
    });
  }

  viewer.addEventListener('load', applyMaterialColor);
  applyMaterialColor();
}

function setupDescribeValidation() {
  var form = document.querySelector('[data-describe-form]');
  if (!form) return;

  var error = document.querySelector('[data-describe-error]');
  var next = form.querySelector('button[type="submit"]');

  function isReady() {
    var description = form.querySelector('textarea[name="description"]').value.trim();
    var area = form.querySelector('input[name="body_area"]').value.trim();
    var quality = Boolean(form.querySelector('input[name="pain_quality[]"]:checked'));
    var onset = Boolean(form.querySelector('input[name="onset"]:checked'));
    return Boolean((description || area) && quality && onset);
  }

  function updateButton() {
    if (!next) return;
    next.disabled = !isReady();
    next.classList.toggle('is-disabled', !isReady());
  }

  form.querySelectorAll('textarea, input').forEach(function (field) {
    field.addEventListener('input', updateButton);
    field.addEventListener('change', updateButton);
  });

  form.addEventListener('submit', function (event) {
    if (!isReady()) {
      event.preventDefault();
      if (error) error.classList.remove('hidden');
    }
  });
  updateButton();
}

function setupScreen3RedFlags() {
  var form = document.querySelector('[data-screen3-form]');
  if (!form) return;

  var modal = form.querySelector('[data-red-flag-modal]');
  var questionsWrap = form.querySelector('[data-red-flag-questions]');
  var close = form.querySelector('[data-red-flag-close]');
  var proceed = form.querySelector('[data-red-flag-continue]');
  var call = form.querySelector('[data-red-flag-call]');
  var triggered = form.querySelector('[data-red-flag-triggered]');
  var emergency = form.querySelector('[data-red-flag-emergency]');
  var bypass = false;

  function selectedAssociated() {
    return Array.from(form.querySelectorAll('input[name="associated_symptoms[]"]:checked')).map(function (input) {
      return input.value;
    });
  }

  function questionSet() {
    var region = form.getAttribute('data-region') || '';
    var onset = form.getAttribute('data-onset') || '';
    var severityInput = form.querySelector('[data-severity-slider]');
    var severity = severityInput ? Number(severityInput.value) : 5;
    var symptoms = selectedAssociated();
    var questions = [];

    if (region.indexOf('head') !== -1 && severity >= 8 && onset.indexOf('suddenly') === 0) {
      questions.push(['worst_headache', 'Is this the worst headache you have ever had?']);
      questions.push(['instant_peak', 'Did it reach full intensity within seconds or a minute or two?']);
    }
    if (region.indexOf('chest') !== -1) {
      questions.push(['shortness_now', 'Do you have shortness of breath right now?']);
      questions.push(['spreading_pain', 'Is the pain spreading to your arm, jaw, or back?']);
    }
    if (region.indexOf('stomach') !== -1 && severity >= 7) {
      questions.push(['rigid_abdomen', 'Is your abdomen rigid or extremely tender to touch?']);
    }
    if (symptoms.indexOf('weakness_one_side') !== -1 || symptoms.indexOf('difficulty_speaking') !== -1) {
      questions.push(['recent_neuro_start', 'Did this start in the last few hours?']);
    }

    return questions;
  }

  function renderQuestions(questions) {
    questionsWrap.innerHTML = '';
    questions.forEach(function (item) {
      var id = item[0];
      var text = item[1];
      var block = document.createElement('fieldset');
      block.className = 'redflag-question';
      block.innerHTML =
        '<legend>' + text + '</legend>' +
        '<label><input type="radio" name="red_flag_' + id + '" value="Yes" required><span>Yes</span></label>' +
        '<label><input type="radio" name="red_flag_' + id + '" value="No" required><span>No</span></label>';
      questionsWrap.appendChild(block);
    });
  }

  function openModal(questions) {
    if (triggered) triggered.value = '1';
    renderQuestions(questions);
    modal.classList.remove('hidden');
    modal.setAttribute('aria-hidden', 'false');
    if (call) call.classList.add('hidden');
  }

  function closeModal() {
    modal.classList.add('hidden');
    modal.setAttribute('aria-hidden', 'true');
  }

  function modalAnswered() {
    var groups = Array.from(questionsWrap.querySelectorAll('.redflag-question'));
    return groups.every(function (group) {
      return Boolean(group.querySelector('input:checked'));
    });
  }

  function anyYes() {
    return Boolean(questionsWrap.querySelector('input[value="Yes"]:checked'));
  }

  form.addEventListener('submit', function (event) {
    if (bypass) return;
    var questions = questionSet();
    if (!questions.length) return;
    event.preventDefault();
    openModal(questions);
  });

  if (close) {
    close.addEventListener('click', closeModal);
  }

  if (proceed) {
    proceed.addEventListener('click', function () {
      if (!modalAnswered()) {
        questionsWrap.classList.add('needs-answer');
        return;
      }
      questionsWrap.classList.remove('needs-answer');
      if (emergency) emergency.value = anyYes() ? '1' : '0';
      if (call) call.classList.toggle('hidden', !anyYes());
      bypass = true;
      form.submit();
    });
  }
}

function setupFollowupAlert() {
  var page = document.querySelector('[data-followup-page]');
  if (!page) return;

  var alert = page.querySelector('[data-chest-alert]');
  var isChest = page.getAttribute('data-is-chest') === 'true';

  function checkedValue(name, value) {
    return Boolean(page.querySelector('input[name="' + name + '"][value="' + value + '"]:checked'));
  }

  function checkedArrayValue(name, value) {
    return Boolean(page.querySelector('input[name="' + name + '[]"][value="' + value + '"]:checked'));
  }

  function update() {
    var chestPain = isChest || checkedArrayValue('warning_symptoms', 'Chest pain');
    var breathing = checkedArrayValue('warning_symptoms', 'Trouble breathing') || checkedValue('conditional_chest_breath', 'Yes');
    var fainting = checkedArrayValue('warning_symptoms', 'Fainting') || checkedValue('conditional_chest_sweat_dizzy_faint', 'Yes');
    var radiating = checkedValue('conditional_chest_radiates', 'Yes');
    var show = chestPain && (breathing || fainting || radiating);

    if (alert) alert.classList.toggle('hidden', !show);
  }

  page.querySelectorAll('input').forEach(function (input) {
    input.addEventListener('change', update);
  });
  update();
}

function setupToggleSections() {
  document.querySelectorAll('[data-toggle-section]').forEach(function (section) {
    var detail = section.querySelector('[data-toggle-detail]');
    var radios = Array.from(section.querySelectorAll('[data-toggle-radio]'));
    if (!detail) return;

    function update() {
      var yes = radios.some(function (radio) {
        return radio.checked && radio.value === 'Yes';
      });
      detail.classList.toggle('hidden', !yes);
    }

    radios.forEach(function (radio) {
      radio.addEventListener('change', update);
    });
    update();
  });
}

function setupResultActions() {
  var saveButton = document.querySelector('[data-save-result]');
  var textArea = document.getElementById('resultDownloadText');
  if (saveButton && textArea) {
    saveButton.addEventListener('click', function () {
      var blob = new Blob([textArea.value], { type: 'text/plain;charset=utf-8' });
      var url = URL.createObjectURL(blob);
      var link = document.createElement('a');
      link.href = url;
      link.download = '60-second-care-result.txt';
      document.body.appendChild(link);
      link.click();
      link.remove();
      URL.revokeObjectURL(url);
    });
  }

  document.querySelectorAll('[data-copy-summary]').forEach(function (button) {
    button.addEventListener('click', function () {
      var summary = document.getElementById('doctorSummary');
      if (!summary) return;

      var value = summary.textContent || '';
      if (navigator.clipboard && navigator.clipboard.writeText) {
        navigator.clipboard.writeText(value);
      } else {
        var temp = document.createElement('textarea');
        temp.value = value;
        document.body.appendChild(temp);
        temp.select();
        document.execCommand('copy');
        temp.remove();
      }

      button.textContent = 'Copied';
      window.setTimeout(function () {
        button.textContent = 'Copy summary';
      }, 1500);
    });
  });
}

function setupFeedback() {
  var form = document.querySelector('[data-feedback-form]');
  if (!form) return;

  var reason = form.querySelector('[data-feedback-reason]');
  var select = reason ? reason.querySelector('select') : null;

  function update() {
    var no = Boolean(form.querySelector('input[name="helpfulness"][value="No"]:checked'));
    if (reason) reason.classList.toggle('hidden', !no);
    if (select) select.required = no;
  }

  form.querySelectorAll('[data-helpfulness-option]').forEach(function (input) {
    input.addEventListener('change', update);
  });
  update();
}
