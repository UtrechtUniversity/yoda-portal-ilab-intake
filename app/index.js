import React, { Component } from "react";
import { render } from "react-dom";
import Form from "react-jsonschema-form";
import Select from 'react-select';
import Geolocation from "./Geolocation"

var schema = {};
var uiSchema = {};
var yodaFormData = {};

var parentHasMetadata = false;
var metadataExists    = false;
var submitButton      = false;
var unsubmitButton    = false;
var locked            = false;
var writePermission   = false;
var save              = false;
var submit            = false;
var unsubmit          = false;
var formDataErrors    = [];

var form = document.getElementById('form');

const customStyles = {
    control: styles => ({...styles, borderRadius: '0px', minHeight: '15px', height: '33.5px'}),
    placeholder: () => ({color: '#555'})
};

const enumWidget = (props) => {
	var enumArray = props["schema"]["enum"];
	var enumNames = props["schema"]["enumNames"];

	if (enumNames == null) {
        enumNames = enumArray;
    }

	var i = enumArray.indexOf(props["value"]);
	var placeholder = enumNames[i] == null ? " " : enumNames[i];

	return (
		<Select
		className={"select-box"}
		placeholder={placeholder}
		required={props.required}
		isDisabled={props.readonly}
		onChange={(event) => props.onChange(event.value)}
		options={props["options"]["enumOptions"]}
		styles={customStyles} />
	);
};

const widgets = {
    SelectWidget: enumWidget
};

const fields = {
    geo: Geolocation
};

const onSubmit = ({formData}) => submitData(formData);

class YodaForm extends React.Component {
    constructor(props) {
        super(props);

        const formContext = {
            submit: false,
            save: false
        };
        this.state = {
            formData: yodaFormData,
            formContext: formContext
        };
    }

    onChange(form) {
        updateCompleteness();

        // Turn save mode off.
        save = false;
        const formContext = {
            submit: false,
            save: false
        };

        this.setState({
            formData: form.formData,
            formContext: formContext
        });
    }

    onError(form) {
        let formContext = {...this.state.formContext};
        formContext.submit = submit;
        formContext.save = save;
        this.setState({
            formContext: formContext
        });
    }

    transformErrors(errors) {
        // console.log(errors);
        if (save) {
            // Only strip errors when saving.
            return errors.filter(function (e) {
                return e.name !== 'required' && e.name !== 'dependencies';
            });
        }

        return errors;
    }

    ErrorListTemplate(props) {
        const {errors, formContext} = props;
        if (!submit) {
            var i = errors.length
            while (i--) {
                if (errors[i].name === "required"     ||
                    errors[i].name === "dependencies") {
                    errors.splice(i,1);
                }
            }
        }

        if (errors.length === 0) {
            return(<div></div>);
        } else {
            // Show error list only on save or submit.
            if (formContext.save || formContext.submit) {
                return (
                  <div className="panel panel-warning errors">
                    <div className="panel-heading">
                      <h3 className="panel-title">Validation warnings</h3>
                    </div>
                    <ul className="list-group">
                      {errors.map((error, i) => {
                        return (
                          <li key={i} className="list-group-item text-warning">
                            {error.stack}
                          </li>
                        );
                      })}
                    </ul>
                  </div>
                );
            } else {
                return(<div></div>);
            }
        }
    }

    render () {
        return (
        <Form className="form form-horizontal metadata-form"
              schema={schema}
              idPrefix={"yoda"}
              uiSchema={uiSchema}
              fields={fields}
              formData={this.state.formData}
              formContext={this.state.formContext}
              ArrayFieldTemplate={ArrayFieldTemplate}
              ObjectFieldTemplate={ObjectFieldTemplate}
              FieldTemplate={CustomFieldTemplate}
              liveValidate={true}
              noValidate={false}
              noHtml5Validate={true}
              showErrorList={true}
              ErrorList={this.ErrorListTemplate}
              onSubmit={onSubmit}
              widgets={widgets}
              onChange={this.onChange.bind(this)}
              onError={this.onError.bind(this)}
              transformErrors={this.transformErrors}>
            <button ref={(btn) => {this.submitButton=btn;}} className="hidden" />
        </Form>
    );
  }
}

class YodaButtons extends React.Component {
    constructor(props) {
        super(props);
    }

    renderSaveButton() {
        return (<button onClick={this.props.saveMetadata} type="submit" className="btn btn-primary">Save</button>);
    }

    renderSubmitButton() {
        return (<button onClick={this.props.submitMetadata} type="submit" className="btn btn-primary">Submit</button>);
    }

    renderUnsubmitButton() {
        return (<button onClick={this.props.unsubmitMetadata} type="submit" className="btn btn-primary">Unsubmit</button>);
    }

    renderDeleteButton() {
        return (<button onClick={deleteMetadata} type="button" className="btn btn-danger delete-all-metadata-btn pull-right">Delete all metadata </button>);
    }

    renderCloneButton() {
        return (<button onClick={this.props.cloneMetadata} type="button" className="btn btn-primary clone-metadata-btn pull-right">Clone from parent folder</button>);
    }

    renderFormCompleteness() {
        return (<span className="form-completeness add-pointer" aria-hidden="true" data-toggle="tooltip" title=""></span>);
    }

    renderButtons() {
        if (writePermission) {
            // Write permission in Research space.
            if (!metadataExists && locked) {
                // Show no buttons.
                return (<div></div>);
            } else if (!metadataExists && parentHasMetadata) {
                // Show 'Save' and 'Clone from parent folder' buttons.
                return (<div>{this.renderSaveButton()} {this.renderFormCompleteness()} {this.renderCloneButton()}</div>);
            } else if (!metadataExists) {
                // Show 'Save' and 'Clone from parent folder' buttons.
                return (<div>{this.renderSaveButton()} {this.renderFormCompleteness()}</div>);
            } else if (!locked && submitButton) {
                // Show 'Save', 'Submit' and 'Delete all metadata' buttons.
                return (<div> {this.renderSaveButton()} {this.renderSubmitButton()} {this.renderFormCompleteness()} {this.renderDeleteButton()}</div>);
            } else if (locked && submitButton) {
                // Show 'Submit' button.
                return (<div>{this.renderSubmitButton()}</div>);
            } else if (!locked && !submitButton) {
                // Show 'Save' and 'Delete all metadata' buttons.
                return (<div>{this.renderSaveButton()} {this.renderFormCompleteness()} {this.renderDeleteButton()}</div>);
            } else if (unsubmitButton) {
                // Show 'Unsubmit' button.
                return (<div>{this.renderUnsubmitButton()}</div>);
            }
        } else {
            // Show no buttons.
            return (<div></div>);
        }
    }

    render() {
        return (
            <div className="form-group">
                <div className="row yodaButtons">
                    <div className="col-sm-12">
                        {this.renderButtons()}
                    </div>
                </div>
            </div>
        );
    }
}


class Container extends React.Component {
    constructor(props) {
        super(props);
        this.saveMetadata = this.saveMetadata.bind(this);
        this.submitMetadata = this.submitMetadata.bind(this);
        this.unsubmitMetadata = this.unsubmitMetadata.bind(this);
    }

    saveMetadata() {
        save = true
        submit = unsubmit = false;
        this.form.submitButton.click();
    }

    submitMetadata() {
        submit = true;
        save = unsubmit = false;
        this.form.submitButton.click();
    }

    unsubmitMetadata() {
        unsubmit = true;
        save = submit = false;
        this.form.submitButton.click();
    }

    cloneMetadata() {
        swal({
            title: "Are you sure?",
            text: "Entered metadata will be overwritten by cloning.",
            type: "warning",
            showCancelButton: true,
            confirmButtonColor: "#ffcd00",
            confirmButtonText: "Yes, clone metadata!",
            closeOnConfirm: false,
            animation: false
        },
        function(isConfirm){
            if (isConfirm) {
                $('#submit-clone').click();
            }
        });
    }

    render() {
        return (
        <div>
          <YodaButtons saveMetadata={this.saveMetadata}
                       submitMetadata={this.submitMetadata}
                       unsubmitMetadata={this.unsubmitMetadata}
                       deleteMetadata={deleteMetadata}
                       cloneMetadata={this.cloneMetadata} />
          <YodaForm ref={(form) => {this.form=form;}}/>
          <YodaButtons saveMetadata={this.saveMetadata}
                       submitMetadata={this.submitMetadata}
                       unsubmitMetadata={this.unsubmitMetadata}
                       deleteMetadata={deleteMetadata}
                       cloneMetadata={this.cloneMetadata} />
        </div>
      );
    }
};


function deleteMetadata() {
    swal({
        title: "Are you sure?",
        text: "You will not be able to recover this action!",
        type: "warning",
        showCancelButton: true,
        confirmButtonColor: "#DD6B55",
        confirmButtonText: "Yes, delete all metadata!",
        closeOnConfirm: false,
        animation: false
    },
    function(isConfirm){
        if (isConfirm) {
            $('#submit-delete').click();
        }
    });
}

function loadForm() {
    // var r = await fetch('/research/metadata/data?path='
    //       +$('#form').attr('data-path'),
    //       {'credentials': 'same-origin'});
    // var data = await r.json();

    var data = JSON.parse(atob($('#form-data').text()));
    // console.log('FORM DATA:');
    // console.log(data);

    // Inhibit "loading" text.
    formLoaded = true;

    schema                   = data.schema;
    uiSchema                 = data.uischema;
    yodaFormData             = data.metadata;
    parentHasMetadata        = data.can_clone;
    const transformationText = data.transformation_text;
    metadataExists           = 'metadata' in data;

    submitButton    = data.is_member && ['SECURED', 'LOCKED', ''].indexOf(data.status) > -1;
    unsubmitButton  = data.is_member && data.status == 'SUBMITTED';
    locked          = ['here', 'ancestor'].indexOf(data.lock_type) > -1;
    writePermission = data.is_member;

    formDataErrors = data.errors;

    if (formDataErrors.length > 0) {
        // Errors exist - show those instead of loading a form.
        var text = '';
        $.each(formDataErrors, function(key, field) {
            text += '<li>' + $('<div />').text(field.replace('->', '→')).html();
        });
        $('.delete-all-metadata-btn').on('click', deleteMetadata);
        $('#form-errors .error-fields').html(text);
        $('#form-errors').removeClass('hide');

    } else if (transformationText !== undefined) {
        // Transformation is necessary. Show transformation prompt.
        $('#transformation-text').html(transformationText);
        if (writePermission) {
            $('#transformation-buttons').removeClass('hide')
            $('#transformation-text').html(transformationText);
        } else {
            $('#transformation .close').removeClass('hide')
        }
        $('.transformation-accept').on('click', function() { $('#submit-transform').click(); });
        $('#transformation').removeClass('hide');

    } else if (!metadataExists && !writePermission) {
        // No metadata present and no write access. Do not show a form.
        $('#form').addClass('hide');
        $('#no-metadata').removeClass('hide');

    } else {
        // Metadata present, load the form.
        if (locked || !writePermission)
            uiSchema['ui:readonly'] = true;

        render(<Container/>,
            document.getElementById('form')
        );

        // Form may already be visible (with "loading" text).
        if ($('#metadata-form').hasClass('hide')) {
            // Avoid flashing things on screen.
            $('#metadata-form').fadeIn(220);
            $('#metadata-form').removeClass('hide');
        }

        updateCompleteness();
    }
}

window.addEventListener('load', loadForm);

function submitData(data)
{
    var path = decodeURIComponent(form.dataset.path);
    var tokenName = form.dataset.csrf_token_name;
    var tokenHash = form.dataset.csrf_token_hash;

    // Disable buttons.
    $('.yodaButtons button').attr('disabled', true);

    // Create form data.
    var bodyFormData = new FormData();
    bodyFormData.set(tokenName, tokenHash);
    bodyFormData.set('data', JSON.stringify({ collection: path,
                                              metadata:   data }));

    // Save.
    $.ajax({
        url: '/research/metadata/save',
        method: 'POST',
        data: bodyFormData,
        processData: false,
        contentType: false,
        success: function (r) {
            window.location.href = '/research/metadata/form?path=' + path;
        },
        error: function (e) {
            console.log('ERROR:');
            console.log(e);
        },
    });
}

function CustomFieldTemplate(props) {
    const {id, classNames, label, help, hidden, required, description, errors, rawErrors, children, displayLabel, formContext, readonly} = props;

    if (hidden || !displayLabel) {
        return children;
    }

    const hasErrors = Array.isArray(errors.props.errors) ? true : false;

    // Only show error messages after submit.
    if (formContext.submit || formContext.save) {
      return (
        <div className={classNames}>
          <label className={'col-sm-2 control-label'}>
            <span data-toggle="tooltip" title="" data-original-title="">{label}</span>
          </label>

          {required ? (
            <span className={'fa-stack col-sm-1'}>
              <i className={'fa fa-lock safe fa-stack-1x'} aria-hidden="true" data-toggle="tooltip" title="" data-original-title="Required for the vault"></i>
              {!hasErrors ? (
                <i className={'fa fa-check fa-stack-1x checkmark-green-top-right'} aria-hidden="true" data-toggle="tooltip" title="" data-original-title="Filled out correctly for the vault"></i>
              ) : (
                null
              )}
            </span>
          ) : (
            <span className={'fa-stack col-sm-1'}></span>
          )}
          <div className={'col-sm-9 field-wrapper'}>
            <div className={'row'}>
              <div className={'col-sm-12'}>
                {description}
                {children}
              </div>
            </div>
            {errors}
            {help}
          </div>
        </div>
      );
    } else {
       return (
        <div className={classNames}>
          <label className={'col-sm-2 control-label'}>
            <span data-toggle="tooltip" title="" data-original-title="">{label}</span>
          </label>

          {required && !readonly ? (
            <span className={'fa-stack col-sm-1'}>
              <i className={'fa fa-lock safe fa-stack-1x'} aria-hidden="true" data-toggle="tooltip" title="" data-original-title="Required for the vault"></i>
              {!hasErrors ? (
                <i className={'fa fa-check fa-stack-1x checkmark-green-top-right'} aria-hidden="true" data-toggle="tooltip" title="" data-original-title="Filled out correctly for the vault"></i>
              ) : (
                null
              )}
            </span>
          ) : (
            <span className={'fa-stack col-sm-1'}></span>
          )}
          <div className={'col-sm-9 field-wrapper'}>
            <div className={'row'}>
              <div className={'col-sm-12'}>
                {description}
                {children}
              </div>
            </div>
            {help}
          </div>
         </div>
      );
    }
}

function ObjectFieldTemplate(props) {
    const { TitleField, DescriptionField } = props;

    var structureClass;
    var structure;
    if ('yoda:structure' in props.schema) {
        var structureClass = 'yoda-structure ' + props.schema['yoda:structure'];
        var structure = props.schema['yoda:structure'];
    }

    if (structure === 'compound') {
        let array = props.properties;
        let output = props.properties.map((prop, i, array) => {
            return (
                <div key={i} className="col-sm-6 field compound-field">
                    {prop.content}
                </div>
            );
        });

        return (
            <div className={"form-group " + structureClass}>
                <label className="col-sm-2 combined-main-label control-label">
                    <span>{props.title}</span>
                </label>
                <span className="fa-stack col-sm-1"></span>
                <div className="col-sm-9">
                    <div className="form-group row">
                        {output}
                    </div>
                </div>
            </div>
        );
    }

    return (
        <fieldset className={structureClass}>
            {(props.uiSchema["ui:title"] || props.title) && (
                <TitleField
                    id={`${props.idSchema.$id}__title`}
                    title={props.title || props.uiSchema["ui:title"]}
                    required={props.required}
                    formContext={props.formContext}
                />
            )}
            {props.description && (
                <DescriptionField
                    id={`${props.idSchema.$id}__description`}
                    description={props.description}
                    formContext={props.formContext}
                />
            )}
            {props.properties.map(prop => prop.content)}
        </fieldset>
    );

}

function ArrayFieldTemplate(props) {
    let array = props.items;
    let canRemove = true;
    if (array.length === 1) {
        canRemove = false;
    }
    let output = props.items.map((element, i, array) => {
        // Read only view
        if (props.readonly || props.disabled) {
            return element.children;
        }

        let item = props.items[i];
        if (array.length - 1 === i) {
            // Render "add" button only on the last item.

            let btnCount = 1;
            if (canRemove) {
                btnCount = 2;
            }

            return (
                <div key={i} className="has-btn">
                    {element.children}
                    <div className={"btn-controls btn-group btn-count-" + btnCount} role="group">
                        {canRemove &&
                        <button type="button" className="clone-btn btn btn-default" onClick={item.onDropIndexClick(item.index)}>
                            <i className="fa fa-minus" aria-hidden="true"></i>
                        </button>}
                        <button type="button" className="clone-btn btn btn-default" onClick={props.onAddClick}>
                            <i className="fa fa-plus" aria-hidden="true"></i>
                        </button>
                    </div>
                </div>
            );
        } else {
            if (canRemove) {
                return (
                    <div key={i} className="has-btn">
                        {element.children}
                        <div className="btn-controls">
                            <button type="button" className="clone-btn btn btn-default" onClick={item.onDropIndexClick(item.index)}>
                                <i className="fa fa-minus" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                )
            }

            return element.children;
        }
    });

    if (props.disabled)
        return (<div class="hide">{output}</div>);
    else
        return (<div>{output}</div>);
}


function updateCompleteness()
{
    var mandatoryTotal = $('.fa-lock.safe:visible').length;
    var mandatoryFilled = $('.fa-stack .checkmark-green-top-right:visible').length;

    if (mandatoryTotal == 0) {
        var metadataCompleteness = 100;
    } else {
        var metadataCompleteness = Math.ceil(100 * mandatoryFilled / mandatoryTotal);
    }

    var html = '<i class="fa fa-check ' + (metadataCompleteness > 19 ? "form-required-present" : "form-required-missing") + '"</i>' +
    '<i class="fa fa-check ' + (metadataCompleteness > 19 ? "form-required-present" : "form-required-missing") + '"></i>' +
    '<i class="fa fa-check ' + (metadataCompleteness > 59 ? "form-required-present" : "form-required-missing") + '"></i>' +
    '<i class="fa fa-check ' + (metadataCompleteness > 79 ? "form-required-present" : "form-required-missing") + '"></i>' +
    '<i class="fa fa-check ' + (metadataCompleteness > 99 ? "form-required-present" : "form-required-missing") + '"></i>';

    $('.form-completeness').attr('title', 'Required for the vault: '+mandatoryTotal+', currently filled required fields: ' + mandatoryFilled);
    $('.form-completeness').html(html);

    return mandatoryTotal == mandatoryFilled;
}
